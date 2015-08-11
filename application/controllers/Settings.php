<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Settings extends CI_Controller {
	function __construct() {
		parent::__construct();
	}
	public function index() {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$data = array();
		$data['settings_material'] = $this->readerself_model->get_settings_material();
		$data['colors'] = array(
			'red' => 'F44336',
			'pink' => 'E91E63',
			'purple' => '9C27B0',
			'deep-purple' => '673AB7',
			'indigo' => '3F51B5',
			'blue' => '2196F3',
			'light-blue' => '03A9F4',
			'cyan' => '00BCD4',
			'teal' => '009688',
			'green' => '4CAF50',
			'light-green' => '8BC34A',
			'lime' => 'CDDC39',
			'yellow' => 'FFEB3B',
			'amber' => 'FFC107',
			'orange' => 'FF9800',
			'deep-orange' => 'FF5722',
			'brown' => '795548',
			'grey' => '9E9E9E',
			'blue-grey' => '607D8B',
			'black' => '000000',
			'white' => 'FFFFFF',
		);
		$data['color_black_text'] = array(
			'light-blue',
			'cyan',
			'green',
			'light-green',
			'lime',
			'yellow',
			'amber',
			'orange',
			'grey',
			'white',
		);

		$this->load->library(array('form_validation'));

		$this->form_validation->set_rules('is_example', 'lang:is_example', 'callback_is_example');

		foreach($data['settings_material'] as $stg) {
			$rules = array();
			$rules[] = 'required';
			if(count($rules) > 0) {
				$this->form_validation->set_rules($stg->stg_code, 'lang:stg_'.$stg->stg_code, implode('|', $rules));
			}
		}

		if($this->form_validation->run() == FALSE) {
			$content = $this->load->view('settings_index', $data, TRUE);
			$this->readerself_library->set_content($content);

		} else {
			if($this->member->mbr_administrator == 1) {
				foreach($data['settings_material'] as $stg) {
					$this->db->set('stg_value', $this->input->post($stg->stg_code));
					$this->db->where('stg_id', $stg->stg_id);
					$this->db->update('settings');
				}
			}
			redirect(base_url().'settings');
		}
	}
	public function goodies() {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$data = array();

		$content = $this->load->view('settings_goodies', $data, TRUE);
		$this->readerself_library->set_content($content);
	}
	public function other() {
		if(!$this->axipi_session->userdata('mbr_id') || !$this->member->mbr_administrator) {
			redirect(base_url());
		}

		$data = array();
		$data['settings'] = $this->readerself_model->get_settings_not_material();

		$data['facebook_error'] = false;
		if($this->config->item('facebook/enabled')) {
			include_once('thirdparty/facebook/autoload.php');
			try {
				$fb = new Facebook\Facebook(array(
					'app_id' => $this->config->item('facebook/id'),
					'app_secret' => $this->config->item('facebook/secret'),
				));
				$fbApp = $fb->getApp();
				$accessToken = $fbApp->getAccessToken();
				$request = new Facebook\FacebookRequest($fbApp, $accessToken, 'GET', 'readerself');
				$response = $fb->getClient()->sendRequest($request);
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				$data['facebook_error'] = 'Facebook: '.$e->getMessage();

			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				$data['facebook_error'] = 'Facebook: '.$e->getMessage();
			}
		}

		$data['readability_error'] = false;
		if($this->config->item('readability_parser_key')) {
			$url = 'https://www.readability.com/api/content/v1/parser?url='.urlencode('https://readerself.com').'&token='.$this->config->item('readability_parser_key');
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			$result = curl_exec($ch);
			curl_close($ch);
			$json = json_decode($result);
			if(isset($json->error) == 1 && isset($json->messages) == 1) {
				$data['readability_error'] = 'Readability: '.$json->messages;
			}
		}

		$this->load->library(array('form_validation'));

		foreach($data['settings'] as $stg) {
			$rules = array();
			if($stg->stg_type == 'email') {
				$rules[] = 'valid_email';
			}
			if($stg->stg_type == 'integer') {
				$rules[] = 'integer';
			}
			if(count($rules) > 0) {
				$this->form_validation->set_rules($stg->stg_code, 'lang:stg_'.$stg->stg_code, implode('|', $rules));
			}
		}

		if($this->form_validation->run() == FALSE) {
			$content = $this->load->view('settings_other', $data, TRUE);
			$this->readerself_library->set_content($content);

		} else {
			if($this->member->mbr_administrator == 1) {
				foreach($data['settings'] as $stg) {
					$this->db->set('stg_value', $this->input->post($stg->stg_code));
					$this->db->where('stg_id', $stg->stg_id);
					$this->db->update('settings');
				}
			}
			redirect(base_url().'settings/other');
		}
	}
	public function update($release = false) {
		if(!$this->axipi_session->userdata('mbr_id') || !$this->member->mbr_administrator) {
			redirect(base_url());
		}

		$data = array();

		$data['entries'] = simplexml_load_file('https://github.com/readerself/readerself/releases.atom');

		if($release && !file_exists('update/'.$release.'.txt')) {
			set_time_limit(120);

			$local_file = 'update/'.$release.'.zip';
			file_put_contents($local_file, file_get_contents('https://github.com/readerself/readerself/archive/'.$release.'.zip'));

			$exclude_files = array(
				'application/config/database.php',
				'application/config/readerself_config.php',
				'application/config/development/database.php',
				'application/config/development/readerself_config.php',
				'application/database/readerself.sqlite',
			);

			$zip = new ZipArchive;
			if($zip->open($local_file) === TRUE) {
				$total_files = $zip->numFiles;
				for($i=0;$i<$total_files;$i++) {
					$file_source = $zip->getNameIndex($i);
					$file_destination = str_replace('readerself-'.$release.'/', '', $file_source);
					if(!in_array($file_destination, $exclude_files)) {
						copy('zip://'.$local_file.'#'.$file_source, $file_destination);
					}
				}
				$zip->close();

				$reverse_releases = array();
				foreach($data['entries']->entry as $entry) {
					$reverse_releases[] = $entry->title;
				}
				$reverse_releases = array_reverse($reverse_releases);

				foreach($reverse_releases as $release_history) {
					if($this->db->dbdriver == 'pdo') {
						$filename = 'application/database/update-sqlite-'.$release_history.'.sql';
					}
					if($this->db->dbdriver == 'mysqli') {
						$filename = 'application/database/update-mysql-'.$release_history.'.sql';
					}
					if(file_exists($filename) && !file_exists('update/'.$release_history.'.txt')) {
						$queries = explode(';', trim(file_get_contents($filename)));
						foreach($queries as $query) {
							if($query != '') {
								$this->db->query($query);
							}
						}
					}

					$fp = fopen('update/'.$release_history.'.txt', 'w');
					fclose($fp);
				}

				unlink($local_file);
			}
			redirect(base_url().'settings/update');
		}

		$content = $this->load->view('settings_update', $data, TRUE);
		$this->readerself_library->set_content($content);
	}
	public function is_example() {
		if($this->member->mbr_email == 'example@example.com') {
			$this->form_validation->set_message('is_example', 'Demo account');
			return FALSE;
		}
		return TRUE;
	}
}
