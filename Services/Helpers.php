<?php

namespace AppBundle\Services;

use DateTime;

class Helpers
{
	private $FCM_url;
	private $FCM_key;

	public function __construct($FMC_url, $FCM_key)
	{
		$this->FCM_url = $FMC_url;
		$this->FCM_key = $FCM_key;
	}

	public function sendFCM($mensaje, $titulo, $id_device, $registration_ids, $param = '0')
	{
		$url = $this->FCM_url;
		if ($id_device) {
			$fields = array(
				'notification' => array(
					'message' => $mensaje,
					'badge' => '1',
					'title' => $titulo,
					'body' => $mensaje,
					'alert' => $mensaje,
					'timestamp' => new DateTime(),
					'idNotificacion' => $param,
					'vibrate' => 1,
					'sound' => 1,
					'click_action' => 'FCM_PLUGIN_ACTIVITY'
				),
				'data' => array(
					'message' => $mensaje,
					'badge' => '1',
					'title' => $titulo,
					'timestamp' => new DateTime(),
					'idNotificacion' => $param,
					'click_action' => 'FCM_PLUGIN_ACTIVITY'
				),
				'to' => $id_device, //destinatario
				'priority' => 'high'
			);
		} else if (count($registration_ids) > 1) {
			$fields = array(
				'notification' => array(
					'message' => $mensaje,
					'badge' => '1',
					'title' => $titulo,
					'body' => $mensaje,
					'alert' => $mensaje,
					'timestamp' => new DateTime(),
					'idNotificacion' => $param,
					'vibrate' => 1,
					'sound' => 1,
					'click_action' => 'FCM_PLUGIN_ACTIVITY'
				),
				'data' => array(
					'message' => $mensaje,
					'badge' => '1',
					'title' => $titulo,
					'timestamp' => new DateTime(),
					'idNotificacion' => $param,
					'click_action' => 'FCM_PLUGIN_ACTIVITY'
				),
				'registration_ids' => $registration_ids, //destinatarios
				'priority' => 'high'
			);
		} else {
			$fields = array(
				'notification' => array(
					'message' => $mensaje,
					'badge' => '1',
					'title' => $titulo,
					'body' => $mensaje,
					'alert' => $mensaje,
					'timestamp' => new DateTime(),
					'idNotificacion' => $param,
					'vibrate' => 1,
					'sound' => 1,
					'click_action' => 'FCM_PLUGIN_ACTIVITY'
				),
				'data' => array(
					'message' => $mensaje,
					'badge' => '1',
					'title' => $titulo,
					'timestamp' => new DateTime(),
					'idNotificacion' => $param,
					'click_action' => 'FCM_PLUGIN_ACTIVITY'
				),
				'to' => '/topics/all', // Todos los dispositivos
				'priority' => 'high'
			);
		}
		$fields = json_encode($fields);
		$headers = array(
			'Authorization: key=' . $this->FCM_key,
			'Content-Type: application/json'
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		$result = curl_exec($ch);
		curl_close($ch);
		error_log($result);
		return $result;
	}
}
