<?php
class Users extends CI_Controller{
	function __construct(){
		//	$this->load->library('database');
			parent::__construct();
	}

	public function index(){
		$users = $this->db->get('users')->result_array();
		if(!empty($users)){
			echo "<table><thead><th>S.No</th><th>Name</th><th>Age</th><th>Created At</th><th>Status</th></thead><tbody>";
			$j = 1;
			foreach($users as $i=>$user){
				echo "<tr>
					<td>".$j."</td>
					<td>".htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8')."</td>
					<td>".htmlspecialchars($user['age'], ENT_QUOTES, 'UTF-8')."</td>
					<td>".htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8')."</td>";
				if($user['status']==1){
					echo "<td style='color: green;'>Active</td>";
				}else{
					echo "<td style='color:red;'>Suspended</td>";
				}	
				echo "</tr>";
				$j++;
			}
			echo "</tbody></table>";
		}
	}


    public function create()
    {
        $this->load->database();
        $this->load->helper(['url']);
    
        // GET: show the form
        if ($this->input->method(TRUE) === 'GET') {
            return $this->load->view('users/create', [
                'error' => '',
                'success' => ''
            ]);
        }
    
        // POST only
        if ($this->input->method(TRUE) !== 'POST') {
            return $this->_json_response(false, 'Invalid request method.', [], 405);
        }
    
        // IMPORTANT: read JSON body (not $_POST, not $_FILES)
        $payload = json_decode($this->input->raw_input_stream, true);
    
        if (!is_array($payload)) {
            return $this->_json_response(false, 'Invalid JSON payload.');
        }
    
        // Basic validation
        $name   = trim((string)($payload['name'] ?? ''));
        $age    = (int)($payload['age'] ?? 0);
        $status = (int)($payload['status'] ?? -1);
    
        if ($name === '' || $age <= 0 || !in_array($status, [0, 1], true)) {
            return $this->_json_response(false, 'Validation failed. Please check inputs.');
        }
    
        // These are R2 OBJECT KEYS (not local paths)
        $imagePath = $payload['image_path'] ?? null;
        $pdfPath   = $payload['pdf_path'] ?? null;
    
        // Optional: basic sanity check (prevent garbage paths)
        if ($imagePath && strpos($imagePath, 'uploads/') !== 0) {
            return $this->_json_response(false, 'Invalid image path.');
        }
    
        if ($pdfPath && strpos($pdfPath, 'uploads/') !== 0) {
            return $this->_json_response(false, 'Invalid PDF path.');
        }
    
        // Insert into DB
        $insert = [
            'name'       => $name,
            'age'        => $age,
            'status'     => $status,
            'image_path' => $imagePath,
            'pdf_path'   => $pdfPath,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    
        if (!$this->db->insert('users', $insert)) {
            return $this->_json_response(false, 'DB insert failed.');
        }
    
        return $this->_json_response(true, 'User created successfully.', [
            'image_path' => $imagePath,
            'pdf_path'   => $pdfPath
        ]);
    }
    

/**
 * Helper to return JSON consistently.
 */
private function _json_response($success, $message, $data = [], $httpCode = 200)
{
    $this->output
        ->set_status_header($httpCode)
        ->set_content_type('application/json', 'utf-8')
        ->set_output(json_encode([
            'success' => (bool)$success,
            'message' => (string)$message,
            'data'    => (object)$data
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        ->_display();
    exit;
}


}
