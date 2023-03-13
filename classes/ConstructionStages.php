<?php

class ConstructionStages
{
	private $db;

	public function __construct()
	{
		$this->db = Api::getDb();
	}

	/**
	 * @param mixed $data = Fields passed from api
	 * @param mixed $action = insert or delete
	 */
	public function validation($data,$action){
		$error = array();
		$data = (array)$data;
		if(isset($data['name']) && $data['name'] != ''){
			if(strlen($data['name']) > 255){
				$error['name'] = "Name length must be less then or equal to 255";
			}
		}else{
			$error['name'] = "Name is required";
		}
		if(isset($data['startDate']) && $data['startDate'] != ''){
			if (!is_string($data['startDate'])) {
				$error['startDate'] = "Invalid format of startDate, must be iso8601";
			}else{
				$dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $data['startDate']);
				if (!$dateTime) {
					$error['startDate'] = "Invalid format of startDate, must be iso8601";
				}
			}		
		}else{
			$error['startDate'] = "startDate is required";
		}

		if(isset($data['endDate']) && $data['endDate'] !=''){
			if (!is_string($data['endDate'])) {
				$error['endDate'] = "Invalid format of endDate, must be iso8601";
			}else{
				$dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $data['endDate']);
				if (!$dateTime) {
					$error['endDate'] = "Invalid format of endDate, must be iso8601";
				}else{
					if($data['endDate'] < $data['startDate']){
						$error['endDate'] = "endDate must be greater then startDate";
					}
				}
			}		
		}else{
			if($action == 'insert'){
				$error['endDate'] = "endDate is required";
			}
		}
		if(isset($data['durationUnit']) && $data['durationUnit'] != ''){
			$accepted_values = array("HOURS","DAYS","WEEKS");
			if(!in_array($data['durationUnit'],$accepted_values)){
				$error['durationUnit'] = "Invalid durationUnit, must be any one of HOURS,DAYS or WEEKS";
			}
		}else{
			$error['durationUnit'] = "durationUnit is required";
		}

		if(isset($data['color']) && $data['color'] !=''){
			if(!preg_match('/^#(?:0x)?[a-f0-9]{1,}$/i', $data['color'])){
				$error['color'] = "Invalid color format, must be valid hexa value.";
			}
		}

		if(isset($data['externalId']) && $data['externalId'] !=''){
			if(strlen($data['externalId']) > 255){
				$error['externalId'] = "externalId length must be less then or equal to 255";
			}
		}
		if(isset($data['status']) && $data['status'] != ''){
			$accepted_values = array("NEW","PLANNED","DELETED");
			if(!in_array($data['status'],$accepted_values)){
				$error['status'] = "Invalid status, must be any one of NEW,PLANNED or DELETED";
			}
		}else{
			$error['status'] = "status is required";
		}

		return $error;

	}
	/**
	 * @param mixed $start
	 * @param mixed $end
	 * @param mixed $durationUnit
	 */
	function dateDifference($start,$end,$durationUnit){
		
		if($durationUnit == 'HOURS'){
			$start_time = strtotime($start);
			$end_time = strtotime($end);
			$diff = abs($end_time - $start_time)/3600;
			return $diff;
		}elseif($durationUnit == 'DAYS'){
			$diff = strtotime($end) - strtotime($start);
			return abs(round($diff / 86400));
		}elseif($durationUnit == 'WEEKS'){
			$diff = strtotime($end) - strtotime($start);
			$days = abs(round($diff / 86400));
			if($days < 7){
				return "0.".$days;
			}else{
				$weeks = $days%7;
				if($weeks == 0){
					$weeks = $days/7;
					$weeks = number_format((float)$weeks, 1, '.', '');
					return $weeks;
				}else{
					$weeks = floor($days/7);
					$day_count = $days%7;
					$weeks = $weeks.".".$day_count;
					return $weeks;
				}
			}
		}else{
			return '';
		}
	}
	public function getAll()
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
		");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getSingle($id)
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
			WHERE ID = :id
		");
		$stmt->execute(['id' => $id]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function post(ConstructionStagesCreate $data)
	{
		$stmt = $this->db->prepare("
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			");
		$stmt->execute([
			'name' => $data->name,
			'start_date' => $data->startDate,
			'end_date' => $data->endDate,
			'duration' => $data->duration,
			'durationUnit' => $data->durationUnit,
			'color' => $data->color,
			'externalId' => $data->externalId,
			'status' => $data->status,
		]);
		return $this->getSingle($this->db->lastInsertId());
	}
	
	/**
	 * @param ConstructionStagesCreate $data
	 * @param mixed $id
	*/
	public function update(ConstructionStagesCreate $data,$id)
	{
		$fields = array();
		$values = array();
		$values['id'] = $id;
		$validation = $this->validation($data,'update');
		if(!empty($validation)){
			return array('status'=>'validation_error',"errors"=>$validation);
		}
		foreach($data as $key => $value){
			if($key != 'duration'){
				if($value !='' && $value !== null && $value !== NULL){
					if($key == 'startDate'){
						$key = "start_date";
					}
					if($key == 'endDate'){
						$key = "end_date";
					}
					$fields[] = "$key=:$key";
					$values[$key] = $value;
				}
			}
		}

		if(isset($data->endDate) && $data->endDate != ''){
			$diff = $this->dateDifference($data->startDate,$data->endDate,$data->durationUnit);
			if($diff == ''){
				if(!empty($validation)){
					return array('status'=>'validation_error',"errors"=>['durationUnit'=>"Invalid duration unit passed"]);
				}
			}else{
				$fields[] = "duration=:duration";
				$values["duration"] = $diff;
			}
		}
		$column = implode(",",$fields);
		$stmt = $this->db->prepare("UPDATE construction_stages SET $column WHERE ID=:id");
		$stmt->execute($values);
		return $this->getSingle($id);
	}

	/**
	* @param mixed $id
	*/
	public function delete($id)
	{
		$values = array();
		$stmt = $this->db->prepare("UPDATE construction_stages SET status='DELETED' WHERE ID=:id");
		$values['id'] = $id;
		if($stmt->execute($values)){
			return true;
		}else{
			return false;
		}
	}
}