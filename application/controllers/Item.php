<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Item extends CI_Controller {
	function __construct() {
		parent::__construct();
	}
	public function pinboard($itm_id, $method = 'add') {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$token = $this->readerself_model->get_token('pinboard', $this->member->mbr_id, false);
		if($this->input->is_ajax_request() && $token) {
			$query = $this->db->query('SELECT * FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.itm_id = ? GROUP BY itm.itm_id', array($itm_id));
			if($query->num_rows() > 0) {
				$itm = $query->row();

				$url = 'https://api.pinboard.in/v1/posts/'.$method;
				$fields = array(
					'auth_token' => $token,
					'url' => $itm->itm_link,
					'description' => $itm->itm_title,
					'replace' => 'yes',
				);
				$ci = curl_init();
				curl_setopt($ci, CURLOPT_URL, $url.'?'.http_build_query($fields));
				curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'GET');
				curl_setopt($ci, CURLOPT_RETURNTRANSFER, 1);
				curl_exec($ci);
			}
		}
	}
	public function star($itm_id) {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$content = array();

		if($this->input->is_ajax_request()) {
			$this->readerself_library->set_template('_json');
			$this->readerself_library->set_content_type('application/json');

			$query = $this->db->query('SELECT * FROM '.$this->db->dbprefix('favorites').' AS fav WHERE fav.itm_id = ? AND fav.mbr_id = ? GROUP BY fav.fav_id', array($itm_id, $this->member->mbr_id));
			if($query->num_rows() > 0) {
				$this->db->where('itm_id', $itm_id);
				$this->db->where('mbr_id', $this->member->mbr_id);
				$this->db->delete('favorites');
				$content['status'] = 'unstar';
				$this->pinboard($itm_id, 'delete');
			} else {
				$this->db->set('itm_id', $itm_id);
				$this->db->set('mbr_id', $this->member->mbr_id);
				$this->db->set('fav_datecreated', date('Y-m-d H:i:s'));
				$this->db->insert('favorites');
				$content['status'] = 'star';
				$this->pinboard($itm_id, 'add');
			}
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
	public function share($itm_id) {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$content = array();

		if($this->input->is_ajax_request()) {
			$this->readerself_library->set_template('_json');
			$this->readerself_library->set_content_type('application/json');

			$query = $this->db->query('SELECT * FROM '.$this->db->dbprefix('share').' AS shr WHERE shr.itm_id = ? AND shr.mbr_id = ? GROUP BY shr.shr_id', array($itm_id, $this->member->mbr_id));
			if($query->num_rows() > 0) {
				$this->db->where('itm_id', $itm_id);
				$this->db->where('mbr_id', $this->member->mbr_id);
				$this->db->delete('share');
				$content['status'] = 'unshare';
				$this->pinboard($itm_id, 'delete');
			} else {
				$this->db->set('itm_id', $itm_id);
				$this->db->set('mbr_id', $this->member->mbr_id);
				$this->db->set('shr_datecreated', date('Y-m-d H:i:s'));
				$this->db->insert('share');
				$content['status'] = 'share';
				$this->pinboard($itm_id, 'add');
			}
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
	public function read($itm_id, $auto = FALSE) {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$type = 'toggle';

		$data = array();

		$content = array();

		if($this->input->is_ajax_request()) {
			$this->readerself_library->set_template('_json');
			$this->readerself_library->set_content_type('application/json');

			$query = $this->db->query('SELECT * FROM '.$this->db->dbprefix('history').' AS hst WHERE hst.itm_id = ? AND hst.mbr_id = ? GROUP BY hst.hst_id', array($itm_id, $this->member->mbr_id));
			if($query->num_rows() > 0) {
				if(!$auto) {
					$this->db->where('itm_id', $itm_id);
					$this->db->where('mbr_id', $this->member->mbr_id);
					$this->db->delete('history');
					$content['status'] = 'unread';
				} else {
					$content['status'] = 'read';
				}
			} else {
				$this->db->set('itm_id', $itm_id);
				$this->db->set('mbr_id', $this->member->mbr_id);
				$this->db->set('hst_datecreated', date('Y-m-d H:i:s'));
				$this->db->insert('history');
				$content['status'] = 'read';
			}
			$content['itm_id'] = $itm_id;

			$content['mode'] = $type;
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
	public function email($itm_id) {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$data = array();

		$content = array();
		$content['itm_id'] = $itm_id;

		if($this->input->is_ajax_request() && $this->config->item('share_external_email')) {
			$query = $this->db->query('SELECT itm.* FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.itm_id = ? GROUP BY itm.itm_id', array($itm_id));
			if($query->num_rows() > 0) {
				$data['itm'] = $query->row();

				//get full content with Readability if key exists
				if($this->config->item('readability_parser_key')) {
					$url = 'https://www.readability.com/api/content/v1/parser?url='.urlencode($data['itm']->itm_link).'&token='.$this->config->item('readability_parser_key');
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_URL, $url);
					$result = curl_exec($ch);
					curl_close($ch);
					$result = json_decode($result);
					$data['itm']->itm_content = $result->content;
				}

				if($data['itm']->auh_id) {
					$sql = 'SELECT auh.* FROM '.$this->db->dbprefix('authors').' AS auh WHERE auh.auh_id = ? GROUP BY auh.auh_id';
					$data['itm']->auh = $this->db->query($sql, array($data['itm']->auh_id))->row();
				} else {
					$data['itm']->auh = false;
				}

				$sql = 'SELECT sub.sub_id, sub.sub_title, fed.fed_title, sub.sub_direction, fed.fed_direction, flr.flr_id, flr.flr_title, flr.flr_direction FROM '.$this->db->dbprefix('subscriptions').' AS sub LEFT JOIN '.$this->db->dbprefix('feeds').' AS fed ON fed.fed_id = sub.fed_id LEFT JOIN '.$this->db->dbprefix('folders').' AS flr ON flr.flr_id = sub.flr_id WHERE sub.fed_id = ? AND sub.mbr_id = ? GROUP BY sub.sub_id';
				$data['itm']->sub = $this->db->query($sql, array($data['itm']->fed_id, $this->member->mbr_id))->row();

				$data['itm']->categories = false;

				if($this->config->item('tags')) {
					$categories = $this->db->query('SELECT tag.* FROM '.$this->db->dbprefix('tags').' AS tag LEFT JOIN '.$this->db->dbprefix('tags_items').' AS tag_itm ON tag_itm.tag_id = tag.tag_id WHERE tag_itm.itm_id = ? GROUP BY tag.tag_id', array($data['itm']->itm_id))->result();
					if($categories) {
						$data['itm']->categories = array();
						foreach($categories as $cat) {
							if(substr($cat->tag_title, 0, 17) == 'foursquare:venue=') {
							} else {
								$data['itm']->categories[] = $cat->tag_title;
							}
						}
					}
				}

				$data['itm']->itm_date = $this->readerself_library->timezone_datetime($data['itm']->itm_date);
				list($data['itm']->explode_date, $data['itm']->explode_time) = explode(' ', $data['itm']->itm_date);

				$this->readerself_library->set_template('_json');
				$this->readerself_library->set_content_type('application/json');

				$this->load->library(array('form_validation'));

				$this->form_validation->set_rules('email_subject', 'lang:email_subject', 'required');
				$this->form_validation->set_rules('email_to', 'lang:email_to', 'required|valid_email');
				//$this->form_validation->set_rules('email_message', 'lang:email_message', '');

				if($this->form_validation->run() == FALSE) {
					$content['modal'] = $this->load->view('item_email', $data, TRUE);
					$content['status'] = 'ko';

				} else {
					$to = $this->input->post('email_to');
					$reply_to = $this->member->mbr_email;
					$subject = $this->input->post('email_subject');
					$message = $this->load->view('share_email', $data, TRUE);
					$this->load->library('email');
					$this->email->clear();

					if($this->config->item('email_protocol') == 'smtp') {
						$this->email->initialize(array('mailtype' => 'html', 'protocol' => 'smtp', 'smtp_host' => $this->config->item('smtp_host'), 'smtp_user' => $this->config->item('smtp_user'), 'smtp_pass' => $this->config->item('smtp_pass'), 'smtp_port' => $this->config->item('smtp_port')));
					} else {
						$this->email->initialize(array('mailtype' => 'html', 'protocol' => 'mail'));
					}
					$this->email->from($this->config->item('sender_email'), $this->config->item('sender_name'));
					$this->email->to($to);
					$this->email->reply_to($reply_to);
					$this->email->subject($subject);
					$this->email->message($message);
					$this->email->send();

					$content['modal'] = $this->load->view('item_email_confirm', $data, TRUE);
					$content['status'] = 'ok';
				}
			} else {
				$this->output->set_status_header(403);
			}
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
	public function expand($itm_id) {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$content = array();

		if($this->input->is_ajax_request()) {
			$this->readerself_library->set_template('_json');
			$this->readerself_library->set_content_type('application/json');

			$query = $this->db->query('SELECT * FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.itm_id = ? GROUP BY itm.itm_id', array($itm_id));
			if($query->num_rows() > 0) {
				$itm = $query->row();

				$sql = 'SELECT enr.* FROM '.$this->db->dbprefix('enclosures').' AS enr WHERE enr.itm_id = ? GROUP BY enr.enr_id ORDER BY enr.enr_type ASC';
				$itm->enclosures = $this->db->query($sql, array($itm->itm_id))->result();

				$itm->itm_content = $this->readerself_library->prepare_content($itm->itm_content);

				$content['itm_id'] = $itm_id;
				$content['itm_content'] = $this->load->view('item_expand', array('itm'=>$itm), TRUE);
			}
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
	public function readability($itm_id) {
		if(!$this->axipi_session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$content = array();

		if($this->input->is_ajax_request() && $this->config->item('readability_parser_key')) {
			$this->readerself_library->set_template('_json');
			$this->readerself_library->set_content_type('application/json');

			$query = $this->db->query('SELECT * FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.itm_id = ? AND itm.fed_id IN ( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.fed_id = itm.fed_id AND sub.mbr_id = ? ) GROUP BY itm.itm_id', array($itm_id, $this->member->mbr_id));
			if($query->num_rows() > 0) {
				$itm = $query->row();

				$content['itm_id'] = $itm->itm_id;

				$url = 'https://www.readability.com/api/content/v1/parser?url='.urlencode($itm->itm_link).'&token='.$this->config->item('readability_parser_key');
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_URL, $url);
				$result = curl_exec($ch);
				curl_close($ch);
				$content['readability'] = json_decode($result);
			}
		} else {
			$this->output->set_status_header(403);
		}
		$this->readerself_library->set_content($content);
	}
}
