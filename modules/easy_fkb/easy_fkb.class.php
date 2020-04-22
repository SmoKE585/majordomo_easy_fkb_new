<?php
class easy_fkb extends module {
	function __construct() {
		$this->name="easy_fkb";
		$this->title="Easy FKB";
		$this->module_category="<#LANG_SECTION_DEVICES#>";
		$this->version="2.1";
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
		$out['MODE']=$this->mode;
		$out['ACTION']=$this->action;
		$this->data=$out;
		$p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
		$this->result=$p->result;
	}

	function admin(&$out) {
		$this->getConfig();
		
		if($this->view_mode == 'settings') {
			if($this->config['SETTINGS_BLOCK'] == 1) {
				$this->config['SETTINGS_BLOCK'] = 0;
			} else {
				$this->config['SETTINGS_BLOCK'] = 1;
			}
			
			$this->saveConfig();
			$this->redirect("?");
		}
		
		if($this->view_mode == 'save_settings') {
			global $ipaddress;
			$this->config['IP'] = $ipaddress;
			global $adminpass;
			$this->config['PASSWORD'] = $adminpass;
			global $logsEnabled;
			$this->config['LOGS_ENABLED'] = $logsEnabled;
			global $timeAutoReload;
			if($timeAutoReload < 60) $timeAutoReload = 60; 
			$this->config['TIME_AUTO_RELOAD'] = $timeAutoReload;
			$this->config['NEXT_DATA_UPDATE'] = '';
			
			$this->config['SETTINGS_BLOCK'] = 0;
			
			$currTime = date('d.m.Y H:s:i', time());
			$arrayRecord = [
				1 => [
					'TITLE' => 'deviceName',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 1,
				],
				2 => [
					'TITLE' => 'freeSpaces',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 1,
				],
				3 => [
					'TITLE' => 'battary',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 1,
				],
				4 => [
					'TITLE' => 'screenBrightness',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
				5 => [
					'TITLE' => 'isScreenOn',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
				6 => [
					'TITLE' => 'plugged',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 1,
				],
				7 => [
					'TITLE' => 'kioskMode',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
				8 => [
					'TITLE' => 'text-to-speech',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
				9 => [
					'TITLE' => 'timeToScreenOff',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
				10 => [
					'TITLE' => 'currentPage',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
				11 => [
					'TITLE' => 'runningApp',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
				12 => [
					'TITLE' => 'setVolume',
					'VALUE' => '',
					'UPDATED' => $currTime,
					'READONLY' => 0,
				],
			];
			
			foreach($arrayRecord as $key => $value) {
				$ifRecord = SQLSelectOne("SELECT `ID` FROM `easy_fkb` WHERE TITLE = '".dbSafe($arrayRecord[$key]["TITLE"])."'");
				
				if(empty($ifRecord['ID'])) {
					SQLInsert('easy_fkb', $arrayRecord[$key]);
				} else {
					SQLUpdate('easy_fkb', $arrayRecord[$key]);
				}
			}
			
			$this->saveConfig();
			
			setGlobal("cycle_{$this->name}", 'start');
			
			$this->redirect("?");
		}
		
		if($this->view_mode == 'update_metrics') {
			$properties = SQLSelect("SELECT * FROM easy_fkb ORDER BY ID");
			$total = count($properties);
			
			for($i = 0; $i < $total; $i++) {
				//debMes('Цикл - '.$total, 'easy_fkb');
				
				$old_linked_object = $properties[$i]['LINKED_OBJECT'];
				$old_linked_property = $properties[$i]['LINKED_PROPERTY'];

				global ${'linked_object'.$properties[$i]['ID']};
				$properties[$i]['LINKED_OBJECT'] = trim(${'linked_object'.$properties[$i]['ID']});

				global ${'linked_property'.$properties[$i]['ID']};
				$properties[$i]['LINKED_PROPERTY'] = trim(${'linked_property'.$properties[$i]['ID']});

				global ${'linked_method'.$properties[$i]['ID']};
				$properties[$i]['LINKED_METHOD'] = trim(${'linked_method'.$properties[$i]['ID']});

				// Если юзер удалил привязанные свойство и метод, но забыл про объект, то очищаем его.
				if ($properties[$i]['LINKED_OBJECT'] != '' && ($properties[$i]['LINKED_PROPERTY'] == '' && $properties[$i]['LINKED_METHOD'] == '')) {
					$properties[$i]['LINKED_OBJECT'] = '';
				}

				// Если юзер удалил только привязанный объект, то свойство и метод тоже очищаем.
				if ($properties[$i]['LINKED_OBJECT'] == '' && ($properties[$i]['LINKED_PROPERTY'] != '' || $properties[$i]['LINKED_METHOD'] != '')) {
					$properties[$i]['LINKED_PROPERTY'] = '';
					$properties[$i]['LINKED_METHOD'] = '';
				}

				if ($old_linked_object && $old_linked_property && ($old_linked_property != $properties[$i]['LINKED_PROPERTY'] || $old_linked_object != $properties[$i]['LINKED_OBJECT'])) {
					removeLinkedProperty($old_linked_object, $old_linked_property, $this->name);
				}

				if ($properties[$i]['LINKED_OBJECT'] && $properties[$i]['LINKED_PROPERTY']) {
					addLinkedProperty($properties[$i]['LINKED_OBJECT'], $properties[$i]['LINKED_PROPERTY'], $this->name);
				}

				SQLUpdate('easy_fkb', $properties[$i]);
			}
			$this->config['NEXT_DATA_UPDATE'] = '';
			$this->saveConfig();
			$this->redirect("?");
		}
		
		if($this->view_mode == 'toggle_screen') {
			if($dataLoad['isScreenOn'] == false) {
				$this->toggleSettings('screenOn');
				$this->config['STATUS_DISPLAY'] = 'Включен';
			} else {
				$this->toggleSettings('screenOff');
				$this->config['STATUS_DISPLAY'] = 'Отключен';
			}
			
			$this->saveConfig();
			$this->redirect("?");
		}

		if($this->getInfomation() == 0) {
			$out['TABLET_NAME'] = $this->config['TABLET_NAME'];
			$out['WIFI_SIGNAL'] = $this->config['WIFI_SIGNAL'];
			$out['WIFI_SSID'] = $this->config['WIFI_SSID'];
			$out['VERSION_APP'] = $this->config['VERSION_APP'];
			$out['TOTAL_SPACE'] = $this->config['TOTAL_SPACE'];
			$out['TOTAL_FREE_SPACE'] = $this->config['TOTAL_FREE_SPACE'];
			$out['BATTARY_LEVEL'] = $this->config['BATTARY_LEVEL'];
			$out['SCREEN_BRIGH'] = $this->config['SCREEN_BRIGH'];
			$out['START_URL'] = $this->config['START_URL'];
			$out['CURRENT_URL'] = $this->config['CURRENT_URL'];
			$out['STATUS_DISPLAY'] = $this->config['STATUS_DISPLAY'];
			$out['STATUS_KIOSK'] = $this->config['STATUS_KIOSK'];
			$out['STATUS_CHARGE'] = $this->config['STATUS_CHARGE'];
			$out['STATUS_DEVICE_ADMIN'] = $this->config['STATUS_DEVICE_ADMIN'];
			$out['MOTION_DETECTED'] = $this->config['MOTION_DETECTED'];
			$out['LAST_DATA_UPDATE'] = date('d.m.Y H:i:s', $this->config['LAST_DATA_UPDATE']);
		}
		
		
		$out['CYCLE_STATUS'] = getGlobal("cycle_{$this->name}");
		$out['PROPERTIES'] = SQLSelect("SELECT * FROM `easy_fkb` ORDER BY ID");
		
		$out['LOGS_ENABLED'] = $this->config['LOGS_ENABLED'];
		$out['ERROR'] = $this->config['ERROR'];
		$out['SETTINGS_BLOCK'] = $this->config['SETTINGS_BLOCK'];
		$out['IP'] = $this->config['IP'];
		$out['PASSWORD'] = $this->config['PASSWORD'];
		$out['TIME_AUTO_RELOAD'] = $this->config['TIME_AUTO_RELOAD'];
		$out['VERSION_MODULE'] = $this->version;
	}
	
	function toggleSettings($cmd, $value = '') {
		return $this->getCURL('http://'.$this->config['IP'].'/?cmd='.$cmd.$value.'&password='.$this->config['PASSWORD'].'&type=json');
	}
	
	function getCURL($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
		$data = curl_exec($ch);
		curl_close($ch);
		
		if($this->config['LOGS_ENABLED'] == 'on') debMes('Запрос cURL - '.$url, 'easy_fkb');
		
		return json_decode($data, TRUE);
	}
	
	function getInfomation() {
		if(time() > $this->config['NEXT_DATA_UPDATE']) {
			$dataLoad = $this->getCURL('http://'.$this->config['IP'].'/?cmd=deviceInfo&password='.$this->config['PASSWORD'].'&type=json');
			$dataLoadSettings = $this->getCURL('http://'.$this->config['IP'].'/?cmd=listSettings&password='.$this->config['PASSWORD'].'&type=json');
		
			if($dataLoad['deviceManufacturer'] != '') {
				$this->config['TABLET_NAME'] = mb_strtoupper($dataLoad['deviceManufacturer']).' '.$dataLoad['deviceName'];
				($dataLoad['wifiSignalLevel'] > 0) ? $this->config['WIFI_SIGNAL'] = 'Подключен' : $this->config['WIFI_SIGNAL'] = 'Не подключен';
				$this->config['WIFI_SSID'] = $dataLoad['ssid'];
				$this->config['VERSION_APP'] = $dataLoad['appVersionName'];
				$this->config['TOTAL_SPACE'] = $dataLoad['internalStorageTotalSpace'];
				$this->config['TOTAL_FREE_SPACE'] = $dataLoad['internalStorageFreeSpace'];
				$this->config['BATTARY_LEVEL'] = $dataLoad['batteryLevel'];
				$this->config['SCREEN_BRIGH'] = $dataLoad['screenBrightness'];
				$this->config['START_URL'] = $dataLoad['startUrl'];
				$this->config['CURRENT_URL'] = $dataLoad['currentPage'];
				
				($dataLoad['isScreenOn'] == true) ? $this->config['STATUS_DISPLAY'] = 'Включен' : $this->config['STATUS_DISPLAY'] = 'Отключен';
				($dataLoad['kioskMode'] == true) ? $this->config['STATUS_KIOSK'] = 'Активирован' : $this->config['STATUS_KIOSK'] = 'Отключен';
				($dataLoadSettings['motionDetection'] == true) ? $this->config['MOTION_DETECTED'] = 'Активирован' : $this->config['MOTION_DETECTED'] = 'Отключен';
				($dataLoad['plugged'] == true) ? $this->config['STATUS_CHARGE'] = 'От сети' : $this->config['STATUS_CHARGE'] = 'От батареи';
				($dataLoad['isDeviceAdmin'] == true) ? $this->config['STATUS_DEVICE_ADMIN'] = 'Активирован' : $this->config['STATUS_DEVICE_ADMIN'] = 'Отключен';
				
				$this->config['LAST_DATA_UPDATE'] = time();
				$this->config['NEXT_DATA_UPDATE'] = time()+$this->config['TIME_AUTO_RELOAD'];
				
				$this->config['ERROR'] = 0;
				
				$currTime = date('d.m.Y H:s:i', time());
				$arrayRecord = [
					1 => [
						'TITLE' => 'deviceName',
						'VALUE' => $this->config['TABLET_NAME'],
						'UPDATED' => $currTime,
					],
					2 => [
						'TITLE' => 'freeSpaces',
						'VALUE' => ($this->config['TOTAL_FREE_SPACE']/1000000),
						'UPDATED' => $currTime,
					],
					3 => [
						'TITLE' => 'battary',
						'VALUE' => $this->config['BATTARY_LEVEL'],
						'UPDATED' => $currTime,
					],
					4 => [
						'TITLE' => 'screenBrightness',
						'VALUE' => $this->config['SCREEN_BRIGH'],
						'UPDATED' => $currTime,
					],
					5 => [
						'TITLE' => 'isScreenOn',
						'VALUE' => ($dataLoad['isScreenOn'] == true) ? 1 : 0,
						'UPDATED' => $currTime,
					],
					6 => [
						'TITLE' => 'plugged',
						'VALUE' => ($dataLoad['plugged'] == true) ? 1 : 0,
						'UPDATED' => $currTime,
					],
					7 => [
						'TITLE' => 'kioskMode',
						'VALUE' => ($dataLoad['kioskMode'] == true) ? 1 : 0,
						'UPDATED' => $currTime,
					],
					8 => [
						'TITLE' => 'timeToScreenOff',
						'VALUE' => $dataLoadSettings['timeToScreenOffV2'],
						'UPDATED' => $currTime,
					],
					9 => [
						'TITLE' => 'currentPage',
						'VALUE' => dbSafe($dataLoad['currentPage']),
						'UPDATED' => $currTime,
						'READONLY' => 0,
					],
					10 => [
						'TITLE' => 'runningApp',
						'VALUE' => '1',
						'UPDATED' => $currTime,
						'READONLY' => 0,
					],
				];
				
				foreach($arrayRecord as $key => $value) {
					//Циклом установим значения и расскидаем по свойствам
					$this->ProcessCommand($arrayRecord[$key]["TITLE"], $arrayRecord[$key]["VALUE"]);
				}
				
				$this->saveConfig();
				
				return 0;
			} else {
				//Поставим флаг 0, что будет значить, что устройство ОФФЛАЙН
				$this->ProcessCommand('runningApp', '0');
				
				//$this->config['TABLET_NAME'] = '';
				//$this->config['ERROR'] = 1;
				//$this->saveConfig();
				
				return 1;
			}
		} else {
			return 0;
		}
	}
	
	function ProcessCommand($title, $value, $params = 0) {
		$cmd_rec = SQLSelectOne("SELECT * FROM `easy_fkb` WHERE `TITLE` = '".DBSafe($title)."'");
		$old_value = $cmd_rec['VALUE'];
		$old_value_in_object = getGlobal($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_PROPERTY']);

		$cmd_rec['VALUE'] = $value;
		$cmd_rec['UPDATED'] = date('d.m.Y H:i:s');
		
		// Обновляем значение метрики в таблице модуля.
		SQLUpdate('easy_fkb', $cmd_rec);
		
		//debMes('Старое значение - '.$old_value.', старое в свойстве - '.$old_value_in_object.' | Новое - '.$value, 'easy_fkb');
		
		// Если значение метрики не изменилось, то выходим.
		if ($old_value == $value && $old_value_in_object == $value) return;
		
		// Иначе обновляем привязанное свойство.
		if ($cmd_rec['LINKED_OBJECT'] && $cmd_rec['LINKED_PROPERTY'] && (getGlobal($cmd_rec['LINKED_OBJECT'] . '.' . $cmd_rec['LINKED_PROPERTY']) != $value)) {
			//debMes('SETGLOBAL - '.$old_value.' | Новое - '.$value, 'easy_fkb');
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
				//Логика для управление экраном
				if($properties[$i]['TITLE'] == 'isScreenOn') {
					((int) strip_tags($value) == 1) ? $newVal = 1 : $newVal = 0;
					
					if($properties[$i]['VALUE'] != $newVal && $newVal == 1) {
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=screenOn&password='.$this->config['PASSWORD'].'&type=json');
					} else if($properties[$i]['VALUE'] != $newVal && $newVal == 0) {
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=screenOff&password='.$this->config['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal);
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
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=textToSpeech&text='.$newVal.'&locale=ru&password='.$this->config['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal);
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
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=setStringSetting&key=timeToScreenOffV2&value='.$newVal.'&password='.$this->config['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal);
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
				//Логика для отправки перейти на страницу
				if($properties[$i]['TITLE'] == 'currentPage') {
					$newVal = strip_tags($value);
					
					if($properties[$i]['VALUE'] != $newVal) {
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=loadUrl&url='.$newVal.'&password='.$this->config['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal);
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
				//Логика для отправки установка громкости
				if($properties[$i]['TITLE'] == 'setVolume') {
					$newVal = (int) strip_tags($value);
					if($newVal > 100) $newVal = 100;
					if($newVal < 0) $newVal = 0;
					
					if($properties[$i]['VALUE'] != $newVal) {
						$titleName = $properties[$i]["TITLE"];
						for($i = 1; $i <= 10; $i++) {
							$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=setAudioVolume&level='.$newVal.'&stream='.$i.'&password='.$this->config['PASSWORD'].'&type=json');
							
							if($responce['status'] == 'OK') {
								$this->ProcessCommand($titleName, $newVal);
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
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=setBooleanSetting&key=kioskMode&value=true&password='.$this->config['PASSWORD'].'&type=json');
					} else if($properties[$i]['VALUE'] != $newVal && $newVal == '0') {
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=setBooleanSetting&key=kioskMode&value=false&password='.$this->config['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal);
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
				//Логика для управлением закрытие программы
				if($properties[$i]['TITLE'] == 'runningApp') {
					((int) $value == '1') ? $newVal = '1' : $newVal = '0';
					
					
					if($properties[$i]['VALUE'] != $newVal && $newVal == '0') {
						$responce = $this->getCURL('http://'.$this->config['IP'].'/?cmd=exitApp&password='.$this->config['PASSWORD'].'&type=json');
					}
					
					if($responce['status'] == 'OK') {
						$this->ProcessCommand($properties[$i]["TITLE"], $newVal);
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Успешно - propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
					} else {
						if($this->config['LOGS_ENABLED'] == 'on') debMes('Ошибка изменения состояния. propertySetHandle->'.$properties[$i]["TITLE"].'->new_val='.$newVal, 'easy_fkb');
						return;
					}
				}
			}
		}
	}
	
	function usual(&$out) {
		$this->admin($out);
		
		$screenBrightness = SQLSelectOne("SELECT `VALUE` FROM `easy_fkb` WHERE TITLE = 'screenBrightness' AND LINKED_OBJECT != '' AND LINKED_PROPERTY != ''");
		$out['SET_SCREEN_BRIGH'] = $screenBrightness['VALUE'];
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
		
		$this->getConfig();
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
        easy_fkb: TITLE varchar(100) NOT NULL DEFAULT ''
        easy_fkb: VALUE text
        easy_fkb: READONLY varchar(10) NOT NULL DEFAULT ''
        easy_fkb: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
        easy_fkb: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
        easy_fkb: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
        easy_fkb: UPDATED varchar(100) NOT NULL DEFAULT ''
EOD;
		parent::dbInstall($data);
   }
}
