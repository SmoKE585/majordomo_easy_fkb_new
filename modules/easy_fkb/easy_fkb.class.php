<?php
class easy_fkb extends module {
	function __construct() {
		$this->name="easy_fkb";
		$this->title="Easy FKB";
		$this->module_category="<#LANG_SECTION_DEVICES#>";
		$this->version="3.0";
		$this->checkInstalled();
	}

	function saveParams($data=1) {
		$p=array();
		if (IsSet($this->id)) {
			$p["id"]=$this->id;
		}
		if (IsSet($this->view_mode)) {
			$p["view_mode"]=$this->view_mode;
		}
		if (IsSet($this->edit_mode)) {
			$p["edit_mode"]=$this->edit_mode;
		}
		if (IsSet($this->tab)) {
			$p["tab"]=$this->tab;
		}
		
		return parent::saveParams($p);
	}

	function getParams() {
		global $id;
		global $mode;
		global $view_mode;
		global $edit_mode;
		global $tab;
		
		if (isset($id)) {
			$this->id=$id;
		}
		if (isset($mode)) {
			$this->mode=$mode;
		}
		if (isset($view_mode)) {
			$this->view_mode=$view_mode;
		}
		if (isset($edit_mode)) {
			$this->edit_mode=$edit_mode;
		}
		if (isset($tab)) {
			$this->tab=$tab;
		}
	}

	function run() {
		global $session;
		$out=array();
		
		if ($this->action=='admin') {
			$this->admin($out);
		} else {
			$this->usual($out);
		}
		if (IsSet($this->owner->action)) {
			$out['PARENT_ACTION']=$this->owner->action;
		}
		if (IsSet($this->owner->name)) {
			$out['PARENT_NAME']=$this->owner->name;
		}
		$out['VIEW_MODE']=$this->view_mode;
		$out['EDIT_MODE']=$this->edit_mode;
		$out['ID']=$this->id;
		$out['MODE']=$this->mode;
		$out['ACTION']=$this->action;
		$this->data=$out;
		$p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
		$this->result=$p->result;
	}
	
	function getAllDevices() {
		$req = SQLSelect('SELECT * FROM easy_fkb_device ORDER BY ID');
		return $req;
	}
	
	function reloadSkills($id) {
		return $this->addDevice('0','0','0','0', $id);
	}
	
	
	function addDevice($protokol, $ipaddress, $port, $adminpass, $reload = 0) {
		if($reload != 0) {
			$device = SQLSelectOne("SELECT * FROM easy_fkb_device WHERE ID = '".DBSafe($reload)."'");
			if(!$device['ID']) return false;
			
			$ipaddress = $device['IP'];
			$adminpass = $device['PASSWORD'];

		} else {
			$ipaddress = $protokol.'://'.$ipaddress.':'.$port;
		}
		
		$responce = $this->getCURL($ipaddress.'/?cmd=deviceInfo&type=json&password='.$adminpass);
		
		if($responce['status'] == 'Error') {
			return false;
		} else if($responce['deviceName']) {
			if($reload == 0) {
				$name = $responce['deviceManufacturer'] .' '. $responce['deviceName'];
				
				$double = SQLSelectOne('SELECT * FROM `easy_fkb_device` WHERE IP = "'.$ipaddress.'"');
				
				if(array_search($name, $double)) return false;
				
				$arrayRecord = [];
				
				$arrayRecord['TITLE'] = $name;
				$arrayRecord['IP'] = $ipaddress;
				$arrayRecord['STATUS'] = 1;
				$arrayRecord['PASSWORD'] = $adminpass;
				$arrayRecord['UPDATED'] = time();
				
				$id = SQLInsert('easy_fkb_device', $arrayRecord);
			} else {
				$id = $reload;
			}
			
			$loadSkills = $this->getBaseInfo($id);
			$recSkills = [];
			$time = time();
			
			foreach($loadSkills[0] as $key => $value) {
				$recSkills['TITLE'] = $value['TITLE'];
				$recSkills['VALUE'] = $value['VALUE'];
				$recSkills['READONLY'] = $value['READONLY'];
				$recSkills['UPDATED'] = $time;
				$recSkills['DEVICE_ID'] = $id;
		
				SQLInsert('easy_fkb', $recSkills);
			}

			return true;
		}
	}
	
	function getBaseInfo($id) {
		$req = SQLSelectOne('SELECT * FROM `easy_fkb_device` WHERE ID = "'.$id.'"');

		$responce = $this->getCURL($req['IP'].'/?cmd=deviceInfo&type=json&password='.$req['PASSWORD']);
		//$responce_sett = $this->getCURL('http://'.$req['IP'].'/?cmd=listSettings&password='.$req['PASSWORD'].'&type=json');
		
		//$responce = array_merge($responce, $responce_sett);
		
		if($responce['status'] == 'Error') {
			return false;
		} else if($responce['deviceName']) {
			$result = [];
			ksort($responce);
			$skillsID = 0;
			
			foreach($responce as $key => $value) {
				if($key == 'webviewUa' || $key == 'androidVersion' || $key == 'androidSdk' || $key == 'appVersionCode' || $key == 'appVersionName' || $key == 'currentFragment' 
				|| $key == 'currentTabIndex' || $key == 'hostname6' || $key == 'ip6') continue;
				
				if($value == true && gettype($value) == 'boolean') {
					$value = '1';
				} else if($value == false && gettype($value) == 'boolean') {
					$value = '0';
				}
				
				if($key == 'isScreenOn' || $key == 'kioskMode' || $key == 'screenBrightness' || $key == 'startUrl') {
					$readonly = 0;
				} else {
					$readonly = 1;
				}
				
				$result[] = [
					'TITLE' => $key,
					'VALUE' => $value,
					'READONLY' => $readonly,
				];
				
				$skillsID++;
			}
			
			//Кастомные навыки
			$result[] = [
				'TITLE' => 'text-to-speech',
				'VALUE' => '',
				'READONLY' => 0,
			];
			$result[] = [
				'TITLE' => 'customCMD',
				'VALUE' => '',
				'READONLY' => 0,
			];
			$result[] = [
				'TITLE' => 'clearCache',
				'VALUE' => '',
				'READONLY' => 0,
			];
			$result[] = [
				'TITLE' => 'timeToScreenOff',
				'VALUE' => '',
				'READONLY' => 0,
			];
			
			return array($result);
		}
	}
	
	function getInfomation($id = '') {
		//Получаем список устройтв
		if($id != '') {
			$filter = "WHERE ID = '".$id."'";
		}
		
		$req = SQLSelect('SELECT * FROM `easy_fkb_device` '.$filter);
		
		foreach($req as $value) {
			//Запращиваем инфу по ним
			$data = $this->getBaseInfo($value['ID']);
			$skillsDB = SQLSelect("SELECT * FROM `easy_fkb` WHERE DEVICE_ID = '".$value['ID']."'");
			$currTime = time();
			
			foreach($data[0] as $key => $upSkills) {
				foreach($skillsDB as $keyDB => $DBData) {				
					if($DBData['TITLE'] == $upSkills['TITLE'] && $DBData['VALUE'] != $upSkills['VALUE']) {
						$this->ProcessCommand($upSkills['TITLE'], $upSkills['VALUE'], array('DEVICE_ID' => $value['ID']));
			
						$skillsDB[$keyDB]['VALUE'] = $upSkills['VALUE'];
						$skillsDB[$keyDB]['UPDATED'] = $currTime;
						SQLUpdate('easy_fkb', $skillsDB[$keyDB]);
					}
				}				
			}
		}
		
	}
	
	function admin(&$out) {
		$this->getConfig();
		
		if($this->view_mode == 'addDevice') {
			global $protokol;
			global $ipaddress;
			global $port;
			global $adminpass;
		
			if($this->addDevice($protokol, $ipaddress, $port, $adminpass)) {
				$this->redirect("?");
			} else {
				//Ошибка
				$out['ADD_DEV_ERROR'] = 1;
				$out['ADD_DEV_IP'] = $ipaddress;
				$out['ADD_DEV_PORT'] = $port;
				$out['ADD_DEV_PASS'] = $adminpass;
			}
		}
		
		if($this->view_mode == 'delDevice' && !empty($this->id)) {
			$properties = SQLSelect("SELECT * FROM easy_fkb WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != '' AND DEVICE_ID = '".DBSafe($this->id)."'");

			if (!empty($properties)) {
				foreach ($properties as $prop) {
					removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
				}
			}
			
			SQLExec("DELETE FROM `easy_fkb_device` WHERE ID = '".DBSafe($this->id)."'");
			SQLExec("DELETE FROM `easy_fkb` WHERE DEVICE_ID = '".DBSafe($this->id)."'");
			
			$this->redirect('?');
		}
		
		if($this->view_mode == 'reloadskills' && !empty($this->id)) {
			$this->reloadSkills(strip_tags($this->id));
			$this->redirect('?');
		}
		
		$allDevice = $this->getAllDevices();
		
		if(!$allDevice || $this->view_mode == 'add') {
			$out['START_PAGE'] = 1;
		} else {
			$out['DEVICES'] = $allDevice;
		}
		
		if($this->view_mode == 'skills' && !empty($this->id)) {
			$this->getInfomation($this->id);
			
			$skills = SQLSelect("SELECT * FROM `easy_fkb` WHERE `DEVICE_ID` = '".DBSafe($this->id)."'");
			$out['BASE_SKILLS'] = $skills;
			
			$skills = SQLSelectOne("SELECT * FROM `easy_fkb_device` WHERE `ID` = '".DBSafe($this->id)."'");
			$out['DEVICE_IP'] = $skills['IP'];
			$out['DEVICE_PASS'] = $skills['PASSWORD'];
			$out['DEVICE_STATUS'] = $skills['STATUS'];
		}
		
		
		if($this->view_mode == 'update_metrics') {
			global $id;
			
			$skills = SQLSelect("SELECT * FROM `easy_fkb` WHERE DEVICE_ID='" . DBSafe($id) . "' ORDER BY ID");
			$total = count($skills);
			for ($i = 0; $i < $total; $i++) {
				$old_linked_object = $skills[$i]['LINKED_OBJECT'];
                $old_linked_property = $skills[$i]['LINKED_PROPERTY'];
				
				global ${'linked_object' . $skills[$i]['ID']};
                $skills[$i]['LINKED_OBJECT'] = trim(${'linked_object' . $skills[$i]['ID']});
                global ${'linked_property' . $skills[$i]['ID']};
                $skills[$i]['LINKED_PROPERTY'] = trim(${'linked_property' . $skills[$i]['ID']});
                global ${'linked_method' . $skills[$i]['ID']};
                $skills[$i]['LINKED_METHOD'] = trim(${'linked_method' . $skills[$i]['ID']});

				SQLUpdate('easy_fkb', $skills[$i]);
				
				if ($old_linked_object != $skills[$i]['LINKED_OBJECT'] && $old_linked_property != $skills[$i]['LINKED_PROPERTY']) {
                    removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
                }
                if ($skills[$i]['LINKED_OBJECT'] && $skills[$i]['LINKED_PROPERTY']) {
                    addLinkedProperty($skills[$i]['LINKED_OBJECT'], $skills[$i]['LINKED_PROPERTY'], $this->name);
                }
				
				
			}
		}
		
		$out['CYCLE_STATUS'] = getGlobal("ThisComputer.cycle_{$this->name}");
		
		$out['VERSION_MODULE'] = $this->version;
	}
	
	function getCURL($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 20); 
		$data = curl_exec($ch);
		curl_close($ch);
		
		if($this->config['LOGS_ENABLED'] == 'on') debMes('Запрос cURL - '.$url, 'easy_fkb');
		
		return json_decode($data, TRUE);
	}
	
	function toggleSettings($cmd, $value) {
		return $this->getCURL('http://'.$this->config['IP'].'/?cmd='.$cmd.$value.'&password='.$this->config['PASSWORD'].'&type=json');
	}
	
	
	function ProcessCommand($title, $value, $params = 0) {		
		$cmd_rec = SQLSelectOne("SELECT * FROM `easy_fkb` WHERE `TITLE` = '".DBSafe($title)."' AND DEVICE_ID = '".$params['DEVICE_ID']."'");
		$old_value = $cmd_rec['VALUE'];
		$old_value_in_object = getGlobal($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_PROPERTY']);

		$cmd_rec['VALUE'] = $value;
		$cmd_rec['UPDATED'] = date('d.m.Y H:i:s');
		
		// Обновляем значение метрики в таблице модуля.
		SQLUpdate('easy_fkb', $cmd_rec);
		
		// Если значение метрики не изменилось, то выходим.
		if ($old_value == $value && $old_value_in_object == $value) return;
		
		// Иначе обновляем привязанное свойство.
		if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_PROPERTY'] && (getGlobal($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_PROPERTY']) != $value)) {
			setGlobal($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_PROPERTY'], $value, array($this->name => '0'));
		}

		// И вызываем привязанный метод.
		if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_METHOD']) {
			if (!is_array($params)) {
				$params = array();
			}

			$params['PROPERTY'] = $title;
			$params['NEW_VALUE'] = $value;
			$params['OLD_VALUE'] = $old_value;

			if ($this->call_method_safe) {
				$this->WriteLog("callMethodSafe({$cmd_rec['LINKED_OBJECT']}.{$cmd_rec['LINKED_METHOD']})");
				callMethodSafe($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_METHOD'], $params);
			} else {
				$this->WriteLog("callMethod({$cmd_rec['LINKED_OBJECT']}.{$cmd_rec['LINKED_METHOD']})");
				callMethod($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_METHOD'], $params);
			}
		}
	}
	
	function propertySetHandle($object, $property, $value) {
		$this->getConfig();

		$properties = SQLSelect("SELECT * FROM `easy_fkb` WHERE LINKED_OBJECT='" . DBSafe($object) . "' AND LINKED_PROPERTY='" . DBSafe($property) . "'");
		$total = count($properties);
		
		if ($total) {
			for ($i = 0; $i < $total; $i++) {
				$devID = SQLSelectOne("SELECT IP,PASSWORD FROM `easy_fkb_device` WHERE ID = '".DBSafe($properties[$i]['DEVICE_ID'])."'");
				
				//Логика для управление экраном
				if($properties[$i]['TITLE'] == 'isScreenOn') {
					((int) strip_tags($value) == 1) ? $newVal = 1 : $newVal = 0;
					
					if($properties[$i]['VALUE'] != $newVal && $newVal == 1) {
						$responce = $this->getCURL($devID['IP'].'/?cmd=screenOn&password='.$devID['PASSWORD'].'&type=json');
					} else if($properties[$i]['VALUE'] != $newVal && $newVal == 0) {
						$responce = $this->getCURL($devID['IP'].'/?cmd=screenOff&password='.$devID['PASSWORD'].'&type=json');
					}
	
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal, array('DEVICE_ID' => $properties[$i]['DEVICE_ID']));
						
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
				//Логика для отправки на синтез речи
				if($properties[$i]['TITLE'] == 'text-to-speech') {
					$newVal = strip_tags($value);
					
					if($properties[$i]['VALUE'] != $newVal) {
						$responce = $this->getCURL($devID['IP'].'/?cmd=textToSpeech&text='.urlencode($newVal).'&locale=ru&password='.$devID['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal, array('DEVICE_ID' => $properties[$i]['DEVICE_ID']));
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
				//Логика для отправки временем работы экрана
				if($properties[$i]['TITLE'] == 'timeToScreenOff') {
					$newVal = (int) strip_tags($value);
					
					if($properties[$i]['VALUE'] != $newVal) {
						$responce = $this->getCURL($devID['IP'].'/?cmd=setStringSetting&key=timeToScreenOffV2&value='.$newVal.'&password='.$devID['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal, array('DEVICE_ID' => $properties[$i]['DEVICE_ID']));
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
				//Логика для отправки перейти на страницу
				// if($properties[$i]['TITLE'] == 'currentPage') {
					// $newVal = strip_tags($value);
					
					// if($properties[$i]['VALUE'] != $newVal) {
						// $responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=loadUrl&url='.$newVal.'&password='.$this->config['PASSWORD'].'&type=json');
					// }
					
					// if($responce['status'] == 'OK') {
						// $this->ProcessCommand($properties[$i]["TITLE"], $newVal);
						// if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					// } else {
						// if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						// return;
					// }
				// }
				//Логика для отправки установка громкости
				if($properties[$i]['TITLE'] == 'setVolume') {
					$newVal = (int) strip_tags($value);
					if($newVal > 100) $newVal = 100;
					if($newVal < 0) $newVal = 0;
					
					if($properties[$i]['VALUE'] != $newVal) {
						$titleName = $properties[$i]["TITLE"];
						for($i = 1; $i <= 10; $i++) {
							$responce = $this->getCURL($devID['IP'].'/?cmd=setAudioVolume&level='.$newVal.'&stream='.$i.'&password='.$devID['PASSWORD'].'&type=json');
							
							if($responce['status'] == 'OK') {
								$this->ProcessCommand($titleName, $newVal, array('DEVICE_ID' => $properties[$i]['DEVICE_ID']));
								if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$titleName.'_'.$i.'->new_val='.$newVal, 'easy_fkb');
							} else {
								if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$titleName.'_'.$i.'->new_val='.$newVal, 'easy_fkb');
								return;
							}
						}
					}
				}
				//Логика для управлением режимом КИОСК
				if($properties[$i]['TITLE'] == 'kioskMode') {
					((bool) $value == true) ? $newVal = '1' : $newVal = '0';
					
					
					if($properties[$i]['VALUE'] != $newVal && $newVal == '1') {
						$responce = $this->getCURL($devID['IP'].'/?cmd=setBooleanSetting&key=kioskMode&value=true&password='.$devID['PASSWORD'].'&type=json');
					} else if($properties[$i]['VALUE'] != $newVal && $newVal == '0') {
						$responce = $this->getCURL($devID['IP'].'/?cmd=setBooleanSetting&key=kioskMode&value=false&password='.$devID['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal, array('DEVICE_ID' => $properties[$i]['DEVICE_ID']));
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
				
				//Логика для управлением ЯРКОСТЬЮ
				if($properties[$i]['TITLE'] == 'screenBrightness') {
					if($properties[$i]['VALUE'] != $value && $value >= '1' && $value <= '255') {
						$responce = $this->getCURL($devID['IP'].'/?cmd=setStringSetting&key=screenBrightness&value='.$value.'&password='.$devID['PASSWORD'].'&type=json');
					}

					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $value, array('DEVICE_ID' => $properties[$i]['DEVICE_ID']));
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$value, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$value, 'easy_fkb');
						return;
					}
				}
				//Логика для кастомных команд
				if($properties[$i]['TITLE'] == 'customCMD') {
					$responce = $this->getCURL($devID['IP'].'/?password='.$devID['PASSWORD'].'&type=json&'.$value);

					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $value, array('DEVICE_ID' => $properties[$i]['DEVICE_ID']));
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$value, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$value, 'easy_fkb');
						return;
					}
				}
			}
		}
	}
	
	function usual(&$out) {
		$this->admin($out);
	}
	
	function DeleteLinkedProperties() {
		$properties = SQLSelect("SELECT * FROM easy_fkb WHERE LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");

		if (!empty($properties)) {
			foreach ($properties as $prop) {
				removeLinkedProperty($prop['LINKED_OBJECT'], $prop['LINKED_PROPERTY'], $this->name);
			}
		}
	}
	
	function DeleteCycleProperties() {
      $cycle_name = 'cycle_' . $this->name;
      $cycle_props = array("{$cycle_name}Run", "{$cycle_name}Control", "{$cycle_name}Disabled", "{$cycle_name}AutoRestart");

      $object = getObject('ThisComputer');

      foreach ($cycle_props as $property) {
         $property_id = $object->getPropertyByName($property, $object->class_id, $object->id);
         if ($property_id) {
            $value_id = getValueIdByName($object->object_title, $property);
            if ($value_id) {
               SQLExec("DELETE FROM phistory WHERE VALUE_ID={$value_id}");
               SQLExec("DELETE FROM pvalues WHERE ID={$value_id}");
            }
            if ($object->class_id != 0) {
               SQLExec("DELETE FROM properties WHERE ID={$property_id}");
            }
          }
      }

      SQLExec("DELETE FROM cached_values WHERE KEYWORD LIKE '%{$cycle_name}%'");
      SQLExec("DELETE FROM cached_ws WHERE PROPERTY LIKE '%{$cycle_name}%'");
   }
	
	function processCycle() {
		setGlobal("cycle_{$this->name}", '1');

		$this->getInfomation();
	}

	function install($data='') {
		//Укажем нормальную версию модуля, а то че как криво. Вообще нужно всем так делать.		
		//SQLExec("UPDATE `plugins` SET `CURRENT_VERSION` = '".dbSafe($this->version)."' WHERE `MODULE_NAME` = '".dbSafe($this->name)."';");
		
		parent::install();
	}
	
	function uninstall() {
		echo '<br>' . date('H:i:s') . " Uninstall module {$this->name}.<br>";

		// Остановим цикл модуля.
		echo date('H:i:s') . " Stopping cycle cycle_{$this->name}.php.<br>";
		setGlobal("cycle_{$this->name}", 'stop');
		// Нужна пауза, чтобы главный цикл обработал запрос.
		$i = 0;
		while ($i < 6) {
		echo '.';
		$i++; 
		sleep(1);
		}

		// Удалим слинкованные свойства объектов у метрик каждого ТВ.
		echo '<br>' . date('H:i:s') . ' Delete linked properties.<br>';
		$this->DeleteLinkedProperties();

		// Удаляем таблицы модуля из БД.
		echo date('H:i:s') . ' Delete DB tables.<br>';
		SQLExec('DROP TABLE IF EXISTS easy_fkb');
		SQLExec('DROP TABLE IF EXISTS easy_fkb_device');

		// Удаляем служебные свойства контроля состояния цикла у объекта ThisComputer.
		echo date('H:i:s') . ' Delete cycles properties.<br>';
		$this->DeleteCycleProperties();

		// Удаляем модуль с помощью "родительской" функции ядра.
		echo date('H:i:s') . ' Delete files and remove frome system.<br>';
		parent::uninstall();
	}
	
	function dbInstall($data = '') {
      $data = <<<EOD
        easy_fkb: ID int(10) unsigned NOT NULL auto_increment
        easy_fkb: DEVICE_ID int(10) NOT NULL DEFAULT '0'
        easy_fkb: TITLE varchar(100) NOT NULL DEFAULT ''
        easy_fkb: VALUE text
        easy_fkb: READONLY int(2) NOT NULL DEFAULT '1'
        easy_fkb: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
        easy_fkb: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
        easy_fkb: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
        easy_fkb: UPDATED varchar(100) NOT NULL DEFAULT ''
		
		easy_fkb_device: ID int(10) unsigned NOT NULL auto_increment
        easy_fkb_device: TITLE varchar(100) NOT NULL DEFAULT ''
        easy_fkb_device: IP varchar(100) NOT NULL DEFAULT ''
        easy_fkb_device: STATUS int(10) NOT NULL DEFAULT '0'
        easy_fkb_device: PASSWORD varchar(100) NOT NULL DEFAULT ''
        easy_fkb_device: UPDATED varchar(100) NOT NULL DEFAULT ''
EOD;
		parent::dbInstall($data);
   }
}
