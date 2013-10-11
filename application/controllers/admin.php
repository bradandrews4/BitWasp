<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Admin extends CI_Controller {

	public $nav;

	public function __construct() {
		parent::__construct();
		$this->load->model('config_model');
		$this->load->model('categories_model');
		
		$this->nav = array(	'' => 			array(	'panel' => '',
													'title' => 'General',
													'heading' => 'Admin Panel'),
							'bitcoin' => 	array(  'panel' => '/bitcoin',
													'title' => 'Bitcoin',
													'heading' => 'Bitcoin Panel'),
							'items' =>		array(	'panel' => '/items',
													'title' => 'Items',
													'heading' => 'Items Panel'),
							'users' => 		array(	'panel' => '/users',
													'title' => 'Users',
													'heading' => 'User Panel'),
							'logs' => 		array(	'panel' => '/logs',
													'title' => 'Logs',
													'heading' => 'Logs Panel')
						);
	}
	
	public function index() {
		$this->load->library('gpg');
		if($this->gpg->have_GPG == TRUE) 
			$data['gpg'] = 'gnupg-'.$this->gpg->version;
		$data['openssl'] = OPENSSL_VERSION_TEXT;
		$data['config'] = $this->bw_config->load_admin('');
		
		$data['page'] = 'admin/index';
		$data['title'] = $this->nav['']['heading'];
		$data['nav'] = $this->generate_nav();
		$this->load->library('Layout', $data);
	}

	public function edit_general() {
		$this->load->library('form_validation');
		$data['config'] = $this->bw_config->load_admin('');
		if($this->form_validation->run('admin_edit_') == TRUE) {
			$changes['site_description'] = ($this->input->post('site_descrpition') !== $data['config']['site_description']) ? $this->input->post('site_description') : NULL;
			$changes['site_title'] = ($this->input->post('site_title') !== $data['config']['site_title']) ? $this->input->post('site_title') : NULL;
			$changes['openssl_keysize'] = ($this->input->post('openssl_keysize') !== $data['config']['openssl_keysize']) ? $this->input->post('openssl_keysize') : NULL;
			$changes['allow_guests'] = ($this->input->post('allow_guests') !== $data['config']['allow_guests']) ? $this->input->post('allow_guests') : NULL;
			$changes = array_filter($changes, 'strlen');
	
			$this->config_model->update($changes);
			redirect('admin');			
		}
		$data['page'] = 'admin/edit_';
		$data['title'] = $this->nav['']['heading'];
		$data['nav'] = $this->generate_nav();
		$this->load->library('Layout', $data);
	}
	
	public function logs() {
		$data['page'] = 'admin/logs';
		$data['title'] = $this->nav['logs']['heading'];

		$data['transaction_count'] = $this->general_model->count_transactions();
		$data['order_count'] = $this->general_model->count_orders();
		$data['messages_count'] = $this->general_model->count_entries('messages');

		$data['intervals'] = $this->config_model->load_autorun_intervals();
		$data['config'] = $this->bw_config->load_admin('logs');
		$data['nav'] = $this->generate_nav();
		
		$this->load->library('Layout', $data);
	}
	
	public function edit_logs() {
		$this->load->library('form_validation');
		$data['page'] = 'admin/edit_logs';
		$data['title'] = $this->nav['logs']['heading'];

		$data['intervals'] = $this->config_model->load_autorun_intervals();
		$data['config'] = $this->bw_config->load_admin('logs');
		$data['nav'] = $this->generate_nav();
		
		$this->load->library('Layout', $data);
	}

	
	public function bitcoin() {
		$this->load->library('bw_bitcoin');
		$this->load->model('bitcoin_model');
		$data['latest_block'] = $this->bitcoin_model->latest_block();
		$data['transaction_count'] = $this->general_model->count_transactions();
		$data['accounts'] = $this->bw_bitcoin->listaccounts(0);
		$data['bitcoin_index'] = $this->bw_config->price_index;
		
		$data['page'] = 'admin/bitcoin';
		$data['title'] = $this->nav['bitcoin']['heading'];
		$data['nav'] = $this->generate_nav();
		$this->load->library('Layout', $data);
	}
	
	public function edit_bitcoin() {
		$this->load->library('form_validation');
		$this->load->library('bw_bitcoin');
		$this->load->model('bitcoin_model');
		$data['config'] = $this->bw_config->load_admin('bitcoin');
		$data['price_index'] = $this->bw_config->price_index;
		$data['accounts'] = $this->bw_bitcoin->listaccounts(0);
		
		if($this->input->post('update_price_index') == 'Update') {
			if(is_array($data['config']['price_index_config'][$this->input->post('price_index')]) || $this->input->post('price_index') == 'Disabled'){
				$update = array('price_index' => $this->input->post('price_index'));
				$this->config_model->update($update);
				if($this->input->post('price_index') !== 'Disabled'){
					
					// If the price index was previously disabled, set the auto-run script interval back up..
					if($data['price_index'] == 'Disabled')
						$this->config_model->set_autorun_interval('price_index', '0.1666');
						
					$this->bw_bitcoin->ratenotify();
				} else {
					// When disabling BPI updates, set the interval to 0.
					$this->config_model->set_autorun_interval('price_index', '0');
				}
				redirect('admin/bitcoin');
			}
		}
		
		if($this->input->post('admin_transfer_bitcoins') == 'Send') {
			if($this->form_validation->run('admin_transfer_bitcoins') == TRUE) {
				$amount = $this->input->post('amount');
				if($data['accounts'][$this->input->post('from')] >= (float)$amount) {
					if($this->bw_bitcoin->move($this->input->post('from'), $this->input->post('to'), (float)$amount) == TRUE)
						redirect('admin/bitcoin');
				} else {
					$data['transfer_bitcoins_error'] = 'That account has insufficient funds.';
				}
			}
		}
		$data['page'] = 'admin/edit_bitcoin';
		$data['title'] = $this->nav['bitcoin']['heading'];
		$data['nav'] = $this->generate_nav();
		$this->load->library('Layout', $data);
	}
	
	public function users() {
		$data['nav'] = $this->generate_nav();
		$data['user_count'] = $this->general_model->count_entries('users');
		$data['config'] = $this->bw_config->load_admin('users');

		$data['page'] = 'admin/users';
		$data['title'] = $this->nav['users']['heading'];
		$this->load->library('Layout', $data);
	}
	
	public function edit_users() {
		$this->load->library('form_validation');
		$data['nav'] = $this->generate_nav();
		$data['config'] = $this->bw_config->load_admin('users');
		
		if($this->form_validation->run('admin_edit_users') == TRUE) {
			$changes['login_timeout'] = ($this->input->post('login_timeout') !== $data['config']['login_timeout']) ? $this->input->post('login_timeout') : NULL;
			$changes['captcha_length'] = ($this->input->post('captcha_length') !== $data['config']['captcha_length']) ? $this->input->post('captcha_length') : NULL;
			$changes['registration_allowed'] = ($this->input->post('registration_allowed') !== $data['config']['registration_allowed']) ? $this->input->post('registration_allowed'): NULL;
			$changes['vendor_registration_allowed'] = ($this->input->post('vendor_registration_allowed') !== $data['config']['vendor_registration_allowed']) ? $this->input->post('vendor_registration_allowed'): NULL;
			$changes['encrypt_private_messages'] = ($this->input->post('encrypt_private_messages') !== $data['config']['encrypt_private_messages']) ? $this->input->post('encrypt_private_message'): NULL;
			$changes['ban_after_inactivity'] = ($this->input->post('ban_after_inactivity') !== $data['config']['ban_after_inactivity']) ? $this->input->post('ban_after_inactivity') : NULL ;			
			if($this->input->post('ban_after_inactivity_disable') == '1')
				$changes['ban_after_inactivity'] = '0';
			
			$changes = array_filter($changes, 'strlen');
		
			// Update config
			$this->config_model->update($changes);
			redirect('admin/users');
		} 
		
		$data['page'] = 'admin/edit_users';
		$data['title'] = $this->nav['users']['heading'];
		$this->load->library('Layout', $data);
	}
	
	// Display admin stats/info about items.
	public function items() {
		$data['nav'] = $this->generate_nav();
		$data['item_count'] = $this->general_model->count_entries('items');
		$data['categories'] = $this->categories_model->list_all();
		$data['page'] = 'admin/items';
		$data['title'] = $this->nav['items']['heading'];
		$this->load->library('Layout', $data);
	}
	
	// Edit Item/Categorys settings, 
	public function edit_items() {
		$this->load->library('form_validation');
		$data['nav'] = $this->generate_nav();
		$data['categories'] = $this->categories_model->list_all();
		
		// Add a new category.
		if($this->input->post('add_category') == 'Add') {
			if($this->form_validation->run('admin_add_category') == TRUE) {
				$category = array(	'name' => $this->input->post('category_name'),
									'hash' => $this->general->unique_hash('categories','hash'),
									'parent_id' => $this->input->post('category_parent'));
				if($this->categories_model->add($category) == TRUE)
					redirect('admin/edit/items');
			} 
		} 
		
		if($this->input->post('rename_category') == 'Rename') {
			if($this->form_validation->run('admin_rename_category') == TRUE) {
				if($this->categories_model->rename($this->input->post('category_id'), $this->input->post('category_name')) == TRUE)
					redirect('admin/edit/items');
			}
		}
		
		// Delete a category
		if($this->input->post('delete_category') == 'Delete') {
			if($this->form_validation->run('admin_delete_category') == TRUE) {
		
				$category = $this->categories_model->get(array('id' => $this->input->post('category_id')));
				$cat_children = $this->categories_model->get_children($category['id']);

				// Check if items or categories are orphaned by this action, redirect to move these.
				if($category['count_items'] > 0 || $cat_children['count'] > 0) {
					echo 'a';
					redirect('admin/category/orphans/'.$category['hash']);
				} else {
					// Delete the category.
					if($this->categories_model->delete($category['id']) == TRUE)
						redirect('admin/edit/items');
				}
			}
		}
		$data['page'] = 'admin/edit_items';
		$data['title'] = $this->nav['items']['heading'];
		$this->load->library('Layout', $data);
	}

	public function category_orphans($hash) {
		$data['category'] = $this->categories_model->get(array('hash' => $hash));
		if($data['category'] == FALSE)
			redirect('admin/items');
			
		$this->load->library('form_validation');
			
		// Load the list of categories.
		$data['categories'] = $this->categories_model->list_all();
		// Load the selected categories children.
		$data['children'] = $this->categories_model->get_children($data['category']['id']);		
		
		// Calculate what text to display.
		if($data['category']['count_items'] > 0 && $data['children']['count'] > 0){
			$data['list'] = "categories and items";
		} else {
			if($data['children']['count'] > 0)				$data['list'] = 'categories';
			if($data['category']['count_items'] > 0)		$data['list'] = 'items';
		}
		
		// If there is nothing to be done for this category, redirect.
		if(!isset($data['list']))
			redirect('admin/edit/items');

		if($this->form_validation->run('admin_category_orphans') == TRUE) {
			// Update records accordingly.
			if($data['list'] == 'items') {
				$this->categories_model->update_items_category($data['category']['id'], $this->input->post('category_id'));
			} else if($data['list'] == 'categories') {
				$this->categories_model->update_parent_category($data['category']['id'], $this->input->post('category_id'));
			} else if($data['list'] == 'categories and items') {
				$this->categories_model->update_items_category($data['category']['id'], $this->input->post('category_id'));
				$this->categories_model->update_parent_category($data['category']['id'], $this->input->post('category_id'));
			}
			// Finally, delete the category.
			if($this->categories_model->delete($data['category']['id']) == TRUE)
				redirect('edit/items');
		}
		
		$data['page'] = 'admin/category_orphans';
		$data['title'] = 'Fix Orphans';
		$this->load->library('Layout', $data);
	}

	public function user_tokens() {
		$this->load->model('users_model');
		$this->load->library('form_validation');
		
		if($this->input->post('create_token') == "Create"){
			if($this->form_validation->run('admin_create_token') == TRUE){
				
				$update = array('user_type' => $this->input->post('user_role'),
								'token_content' => $this->general->unique_hash('registration_tokens','token_content', 128),
								'comment' => $this->input->post('token_comment'));
								
				$data['returnMessage'] = 'Unable to create your token at this time.';
				if($this->users_model->add_registration_token($update) == TRUE){
					$data['success'] = TRUE;
					$data['returnMessage'] = 'Your token has been created';
				} 
			}
		}
		
		$data['tokens'] = $this->users_model->list_registration_tokens();
		$data['page'] = 'admin/user_tokens';
		$data['title'] = 'Registration Tokens';
		$this->load->library('Layout', $data);
	}
	
	public function delete_token($token) {
		$this->load->library('form_validation');
		$this->load->model('users_model');
		
		$token = $this->users_model->check_registration_token($token);
		if($token == FALSE)
			redirect('admin/tokens');
			
		$data['returnMessage'] = 'Unable to delete the specified token, please try again later.';
		if($this->users_model->delete_registration_token($token['id']) == TRUE){
			$data['success'] = TRUE;
			$data['returnMessage'] = 'The selected token has been deleted.';
		}
			
		$data['tokens'] = $this->users_model->list_registration_tokens();
		$data['page'] = 'admin/user_tokens';
		$data['title'] = 'Registration Tokens';
		$this->load->library('Layout', $data);
			
		return FALSE;
	}

	public function delete_item($hash) {
		$this->load->library('form_validation');
		$this->load->model('items_model');
		$this->load->model('messages_model');
		
		$data['item'] = $this->items_model->get($hash);
		if($data['item'] == FALSE)
			redirect('items');
			
		$data['title'] = 'Delete Item';
		$data['page'] = 'admin/delete_item';			
		
		if($this->form_validation->run('admin_delete_item') == TRUE) {
			if($this->items_model->delete($data['item']['id']) == TRUE) {
				
				$info['from'] = $this->current_user->user_id;
				$details = array('username' => $data['item']['vendor']['user_name'],
								 'subject' => "Listing '{$data['item']['name']}' has been removed");
				$details['message'] = "Your listing has been removed from the marketplace. <br /><br />\n";
				$details['message'] = "Reason for removal:<br />\n".$this->input->post('reason_for_removal');
				$message = $this->bw_messages->prepare_input($info, $details);
				$this->messages_model->send($message);
				
				$data['title'] = 'Deleted Item';
				$data['page'] = 'items/index';
				$data['items'] = $this->items_model->get_list();					
			} else { 
				$data['returnMessage'] = 'Unable to delete that item at this time.';
			}
		}
		$this->load->library('Layout', $data);
	}
	

	public function ban_user($hash) {
		$this->load->library('form_validation');
		$this->load->model('accounts_model');
		
		$data['user'] = $this->accounts_model->get(array('user_hash' => $hash));
		if($data['user'] == FALSE)
			redirect('admin/edit/users');
			
		$data['title'] = 'Ban User';
		$data['page'] = 'admin/ban_user';			
		
		if($this->form_validation->run('admin_ban_user') == TRUE) {
			if($this->input->post('ban_user') !== $data['user']['banned']) {
				if( $this->accounts_model->toggle_ban($data['user']['id'], $this->input->post('ban_user') ) ) {
					$data['returnMessage'] = $data['user']['user_name']." has now been ";
					$data['returnMessage'].= ($this->input->post('ban_user') == '0') ? 'banned.' : 'unbanned.'; 
					$data['page'] = 'accounts/view';
					$data['title'] = $data['user']['user_name'];
					
					$data['logged_in'] = $this->current_user->logged_in();
					$data['user_role'] = $this->current_user->user_role;
					$data['user'] = $this->accounts_model->get(array('user_hash' => $hash));					
				} else {
					$data['returnMessage'] = 'Unable to alter this user right now, please try again later.';
				}
			} else {
				redirect('user/'.$data['user']['user_hash']);
			}
		}
				
		$this->load->library('Layout', $data);
	}	

	// Generate the navigation bar for the admin panel.
	public function generate_nav() { 
		$links = '';
		
		foreach($this->nav as $entry) { 
			$links .= '<li';
			if(uri_string() == 'admin'.$entry['panel'] || uri_string() == 'admin/edit'.$entry['panel']) {
				$links .= ' class="active" ';
				$self = $entry;
				$heading = $entry['heading'];
			}
			$links .= '>'.anchor('admin'.$entry['panel'], $entry['title']).'</li>';
		}

		$nav = '
		  <div class="tabbable">
			<label class="span3"><h2>'.$self['heading'].'</h2></label>
			<label class="span1"><a href="'.site_url().'admin/edit'.$self['panel'].'" class="btn ">Edit</a></label>
			<label class="span7">
			  <ul class="nav nav-tabs">
			  '.$links.'
			  </ul>
			</label>
		  </div>';

		return $nav;
	}
	
	// Callback to check the captcha length is not too long.
	public function check_captcha_length($param) {
		return ($param > 0 && $param < 13) ? TRUE : FALSE;
	}

	// Callback functions for form validation.
	public function check_bool($param) {
		return ($this->general->matches_any($param, array('0','1')) == TRUE) ? TRUE : FALSE;
	}

	// Callback; check the required category exists (for parent_id)
	public function check_category_exists($param) {
		if($param == '0')	// Allows the category to be a root category.
			return TRUE;
			
		return ($this->categories_model->get(array('id' => $param)) !== FALSE) ? TRUE : FALSE;
	}
	
	// Callback, check if the category can be deleted.
	public function check_can_delete_category($param) {
		return ($this->categories_model->get(array('id' => $param)) !== FALSE) ? TRUE : FALSE;
	}
	
	// Callback: check the specified bitcoin account already exists.
	public function check_bitcoin_account_exists($param) {
		if($param == '')
			return FALSE;
			
		$accounts = $this->bw_bitcoin->listaccounts(0);
		return (isset($accounts[$param])) ? TRUE : FALSE;
	}
	
	// Check the submitted parameter is either 1, 2, or 3.
	public function check_admin_roles($param){
		return ($this->general->matches_any($param, array('1','2','3')) == TRUE) ? TRUE : FALSE;
	}
	
	public function is_positive($param) {
		return ($param > 0) ? TRUE : FALSE;
		
	}
};

/* End of file: Admin.php */
