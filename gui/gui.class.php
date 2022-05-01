<?php
class Gui {
	private $mysqli;

	public function __construct() {
		require('config.php');

		$this->mysqli = new mysqli($dbhost, $dbname, $dbpass, $dbname);

		if($this->mysqli->connect_error) {
		  throw new Exception('Error connecting to MySQL: '.$this->mysqli->connect_error);
		}
	}

	public function __destruct() {
		$this->mysqli->close();
	}

	public function signIn($apikey) {
	    $query = $this->mysqli->prepare("SELECT `iduser` FROM `users` WHERE `apikey` =  ?");
	    $query->bind_param('s', $apikey);

		if($query->execute()) {
			$query->bind_result($iduser);
			$query->fetch();

			if($iduser) {
				return $iduser;
			}
		}

	    return false;
	}

	public function getLastRecord($uid) {
		$query = $this->mysqli->prepare("SELECT `timestamp`, `carType`, `ignitionOn`, `chargingOn`, `socPerc`, `socPercBms`, `sohPerc`, `batPowerKw`, `batPowerAmp`, `batVoltage`, `auxVoltage`, `batMinC`, `batMaxC`, `batInletC`, `extTemp`, `batFanStatus`, `cumulativeEnergyChargedKWh`, `cumulativeEnergyDischargedKWh`, `odoKm`, `speedKmh`, `gpsLat`, `gpsLon`, `gpsAlt` FROM `data` WHERE `user` = ? ORDER BY `timestamp` DESC LIMIT 1");

		$query->bind_param('i', $uid);

		if($query->execute()) {
			return $query->get_result()->fetch_object();
		}
	}

	public function getSettings($uid) {
		$query = $this->mysqli->prepare("SELECT `timezone`, `notifications`, `abrp_enabled`, `abrp_token` FROM `users` WHERE `iduser` = ?");
		$query->bind_param('i', $uid);

		if($query->execute()) {
			return $query->get_result()->fetch_object();
		}
	}

	public function setSettings($uid, $timezone, $notifications, $abrp_enabled, $abrp_token) {
	    $query = $this->mysqli->prepare("UPDATE `users` SET `timezone` = ?, `notifications` = ?, `abrp_enabled` = ?, `abrp_token` = ? WHERE `iduser` = ?");
	    $query->bind_param('siisi', $timezone, $notifications, $abrp_enabled, $abrp_token, $uid);

	    if($query->execute()) {
	      return true;
	    } else {
	      return false;
	    }
	}

	public function getChargingSessions($uid) {
	  $query = $this->mysqli->prepare("select * from (select odoKm, round(max(cumulativeEnergyChargedKWh) - min(cumulativeEnergyChargedKWh),3) as kwh from data where user = ? group by odoKm) as x where kwh>1;");
		$query->bind_param('s', $uid);

		if(!$query->execute()) {
			return false;
		}
    $result = $query->get_result();
    $return = array();
    $i = 0;
    while ($obj = $result->fetch_object()) {
      $return[$i]['odoKm'] = $obj->odoKm;
      $return[$i]['kwh'] = $obj->kwh;
      $i++;
    }
    return $return;
  }

	public function getUsage($uid, $odoMin = 0) {
    $charged = $this->getTotalCharged($uid, $odoMin);;
	  $query = $this->mysqli->prepare("select avg(extTemp) as extTemp, max(odoKm) - min(odoKm) as km, round(max(cumulativeEnergyChargedKWh) - min(cumulativeEnergyChargedKWh),3) as charged, round(max(cumulativeEnergyDischargedKWh) - min(cumulativeEnergyDischargedKWh),3) as discharged from data where user = ? and odoKm > ?");
		$query->bind_param('ss', $uid, $odoMin);

		if(!$query->execute()) {
			return false;
		}

    $result = $query->get_result();
    $return = array();
    $obj = $result->fetch_object();
    $return['charged'] = $charged;
    $return['recuperated'] = $obj->charged - $charged;
    $return['discharged'] = $obj->discharged;
    $return['km'] = $obj->km;
    $return['tempC'] = $obj->extTemp;
    $return['wkm'] = round(1000 * ($return['discharged'] - $return['recuperated']) / $return['km']);
    return $return;
  }

  public function getLastUsage($uid) {
    $odoKm = 0;
    foreach ($this->getChargingSessions($uid) as $obj) {
      $odoKm= $obj['odoKm'];
    }
    return $this->getUsage($uid, $odoKm);
  }

  public function getTotalCharged($uid, $odoMin) {
    $charged = 0;
    foreach ($this->getChargingSessions($uid) as $obj) {
      if ($odoMin < $obj['odoKm']) {
        $charged += $obj['kwh'];
      }
    }
    return $charged;
  }

	public function getChargingListByOdoKm($uid) {
	  $query = $this->mysqli->prepare("select * from (select odoKm, carType, min(socPerc) as min_perc, max(socPerc) as max_perc, round(max(cumulativeEnergyChargedKWh) - min(cumulativeEnergyChargedKWh),3) as kwh, gpsLat, gpsLon, min(timestamp) as start, max(timestamp) as end from data where user=? group by odoKm) as x where kwh>1;");
		$query->bind_param('s', $uid);

		if(!$query->execute()) {
			return false;
		}
    $result = $query->get_result();

    $return = array();
    $i = 0;

    while ($obj = $result->fetch_object()) {
      $return[$i]->kwh = $obj->kwh;
      $return[$i]->min_perc = (int) $obj->min_perc;
      $return[$i]->max_perc = (int) $obj->max_perc;
      $return[$i]->carType = $obj->carType;
      $return[$i]->gps_lat = $obj->gpsLat;
      $return[$i]->gps_lon = $obj->gpsLon;
      $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $obj->gpsLat . "," . $obj->gpsLon .  "&key=XXXXX";
      error_log($url);
      $resp_json = file_get_contents($url);
      $resp = json_decode($resp_json, true);
      $return[$i]->address = $resp['results'][0]['formatted_address'];
      $return[$i]->carType = $obj->carType;
      $return[$i]->timestamp = $obj->start;
      $return[$i]->odoKm = $obj->odoKm;
      $i++;
    }
    return $return;
  }

	public function getChargingListNew($uid) {
	  $query = $this->mysqli->prepare("SELECT `odoKm`, `chargingOn`, `batPowerKw`, `cumulativeEnergyChargedKWh`, `timestamp`, `socPerc`, `gpsLat`, `gpsLon` FROM `data` WHERE `user` = ? ORDER BY `timestamp` ASC");
		$query->bind_param('s', $uid);

		if(!$query->execute()) {
			return false;
		}
    $result = $query->get_result();

    $return = array();

    $last_obj = Null;
    $i = 0;
    $last_charging = 0;
    $last_kwh = 0;

    while ($obj = $result->fetch_object()) {
        if(!$last_obj) {
          $last_obj = clone $obj;
			  }
        if (!$last_charging) {
          $last_charging = $obj->chargingOn;
        }
        if (!$last_kwh) {
          $last_kwh = $obj->cumulativeEnergyChargedKWh;
        }
        $charging_end = False;
        if ($obj->chargingOn != $last_charging) {
          if ($obj->chargingOn == 0) {
            $charging_end = True;
          }
        } else if ($obj->chargingOn == 0 && $obj->cumulativeEnergyChargedKWh - $last_kwh > 1) {
          $charging_end = True;
        }
        if ($charging_end) {
          $return[$i]->kwh = $obj->cumulativeEnergyChargedKWh - $last_kwh;
          $return[$i]->min_perc = (int) $last_obj->socPerc ;
          $return[$i]->max_perc = (int) $obj->socPerc;
          $return[$i]->gps_lat = $last_obj->gpsLat;
          $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=" . $obj->gpsLat . "," . $obj->gpsLon .  "&key=XXXXX";
          error_log($url);
          $resp_json = file_get_contents($url);
          $resp = json_decode($resp_json, true);
          $return[$i]->address = $resp['results'][0]['formatted_address'];
          $return[$i]->gps_lon = $last_obj->gpsLon;
          $return[$i]->carType = $obj->carType;
          $return[$i]->is_dc = False;
          $return[$i]->timestamp = $last_obj->timestamp;
          $return[$i]->odoKm = $last_obj->odoKm;
          $i++;
        }
        if ($obj->chargingOn == 0) {
          $last_obj = $obj;
          $last_charging = $obj->chargingOn;
          $last_kwh = $obj->cumulativeEnergyChargedKWh;
        }
      }
    return $return;
  }

	public function getChargingList($uid, $date_from = null, $date_to = null, $lat = null, $lon = null) {
		if(!isset($date_from, $date_to)) {
			$date_from = date('Y-m-d h:m:s', mktime(0, 0, 0, date("m"), 1, date("Y")));
			$date_to = date('Y-m-d h:m:s', mktime(23, 59, 59, date("m")+1, 0, date("Y")));
		}

		if(isset($lat, $lon)) {
			$query = $this->mysqli->prepare("SELECT `iddata`, `timestamp`, `carType`, `socPerc`, `batPowerKw`, `cumulativeEnergyChargedKWh`, `gpsLat`, `gpsLon` FROM `data` WHERE `user` = ? AND `gpsLat` < ? + 0.02 AND `gpsLat` > ? + -0.02 AND `gpsLon` < ? + 0.02 AND `gpsLon` > ? + -0.02 AND `chargingOn` = 1 AND `timestamp` >= ? AND `timestamp` <= ? ORDER BY `timestamp` DESC");
			$query->bind_param('iddddss', $uid, $lat, $lat, $lon, $lon, $date_from, $date_to);
		} else {
			$query = $this->mysqli->prepare("SELECT `iddata`, `timestamp`, `carType`, `socPerc`, `batPowerKw`, `cumulativeEnergyChargedKWh`, `gpsLat`, `gpsLon` FROM `data` WHERE `user` = ? AND `chargingOn` = 1 AND `timestamp` >= ? AND `timestamp` <= ? ORDER BY `timestamp` DESC");
			$query->bind_param('iss', $uid, $date_from, $date_to);
		}

		if(!$query->execute()) {
			return false;
		}
    $result = $query->get_result();
    $return = array();

		$last_timestamp = 0;
		$max_kwh = 0;
		$last_kwh = 0;
		$max_perc = 0;
		$last_perc = 0;
		$is_dc = false;
		$i = 0;

    while ($obj = $result->fetch_object()) {
			if(!$max_kwh || !$max_perc) {
				$max_kwh = $obj->cumulativeEnergyChargedKWh;
				$max_perc = $obj->socPerc;
			}
			if($last_timestamp > strtotime($obj->timestamp) + 1800) {
				$return[$i]->kwh = $max_kwh - $last_kwh;
				$return[$i]->max_perc = $max_perc;
				$return[$i]->carType = $obj->carType;
				$return[$i]->is_dc = $is_dc;
				$i++;
				$max_kwh = $obj->cumulativeEnergyChargedKWh;
				$max_perc = $obj->socPerc;
				$is_dc = false;
			}
			if($obj->batPowerKw > 22) {
				$is_dc = true;
			}
			$return[$i]->timestamp = $obj->timestamp;
			$last_kwh = $obj->cumulativeEnergyChargedKWh;
			$return[$i]->min_perc = $obj->socPerc;
			$return[$i]->gps_lat = $obj->gpsLat;
			$return[$i]->gps_lon = $obj->gpsLon;
			$return[$i]->iddata = $obj->iddata;
			$last_timestamp = strtotime($obj->timestamp);
    }

    return $return;
	}
}
?>
