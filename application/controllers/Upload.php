<?php
use Aws\S3\S3Client;

defined('BASEPATH') OR exit('No direct script access allowed');

class Upload extends CI_Controller
{
    private $s3;
    private $bucket;
    private $cdnBase;

    public function __construct()
    {
        parent::__construct();
        
        // Load .env file directly if not already loaded (fallback)
        $this->_loadEnvFile();
        
        // Try getenv first, then $_ENV, then $_SERVER, then CodeIgniter config
        $this->bucket  = getenv('R2_BUCKET') 
            ?: (isset($_ENV['R2_BUCKET']) ? $_ENV['R2_BUCKET'] : null)
            ?: (isset($_SERVER['R2_BUCKET']) ? $_SERVER['R2_BUCKET'] : null)
            ?: $this->config->item('r2_bucket');
        $this->cdnBase = rtrim(
            getenv('R2_PUBLIC_BASE') 
                ?: (isset($_ENV['R2_PUBLIC_BASE']) ? $_ENV['R2_PUBLIC_BASE'] : null)
                ?: (isset($_SERVER['R2_PUBLIC_BASE']) ? $_SERVER['R2_PUBLIC_BASE'] : null)
                ?: $this->config->item('r2_public_base'),
            '/'
        );

        if (!$this->bucket || !$this->cdnBase) {
            log_message('error', 'R2 configuration missing: R2_BUCKET or R2_PUBLIC_BASE not set');
        }

        $endpoint = getenv('R2_ENDPOINT') 
            ?: (isset($_ENV['R2_ENDPOINT']) ? $_ENV['R2_ENDPOINT'] : null)
            ?: (isset($_SERVER['R2_ENDPOINT']) ? $_SERVER['R2_ENDPOINT'] : null)
            ?: $this->config->item('r2_endpoint');
        $accessKey = getenv('R2_ACCESS_KEY') 
            ?: (isset($_ENV['R2_ACCESS_KEY']) ? $_ENV['R2_ACCESS_KEY'] : null)
            ?: (isset($_SERVER['R2_ACCESS_KEY']) ? $_SERVER['R2_ACCESS_KEY'] : null)
            ?: $this->config->item('r2_access_key');
        $secretKey = getenv('R2_SECRET_KEY') 
            ?: (isset($_ENV['R2_SECRET_KEY']) ? $_ENV['R2_SECRET_KEY'] : null)
            ?: (isset($_SERVER['R2_SECRET_KEY']) ? $_SERVER['R2_SECRET_KEY'] : null)
            ?: $this->config->item('r2_secret_key');


        if (!$endpoint || !$accessKey || !$secretKey) {
            log_message('error', 'R2 credentials missing');
        }

        $this->s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'auto',
            'endpoint'    => $endpoint,
            'credentials' => [
                'key'    => $accessKey,
                'secret' => $secretKey,
            ],
        ]);
    }

    /**
     * Generate signed PUT URL
     * POST: filename, mime
     */
    public function signed_url()
    {
        // Check if R2 is configured
        if (!$this->bucket || !$this->cdnBase) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Storage service not configured']));
        }

        // 1. Basic validation
        $filename = $this->input->post('filename');
        $mime     = $this->input->post('mime');

        if (!$filename || !$mime) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Invalid request']));
        }

        // 2. Allow only safe mime types (example)
        $allowed = [
            'image/jpeg',
            'image/png',
            'application/pdf'
        ];

        if (!in_array($mime, $allowed)) {
            return $this->output
                ->set_status_header(400)
                ->set_output(json_encode(['error' => 'Invalid file type']));
        }

        // 3. Generate safe object key
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $objectKey = sprintf(
            'uploads/%s/%s.%s',
            date('Y/m/d'),
            bin2hex(random_bytes(16)),
            $ext
        );

        // 4. Create signed PUT command
        $cmd = $this->s3->getCommand('PutObject', [
            'Bucket'        => $this->bucket,
            'Key'           => $objectKey,
            'ContentType'   => $mime,
            'CacheControl'  => 'public, max-age=31536000, immutable',
        ]);

        // 5. Generate signed URL (valid for 5 minutes)
        $request = $this->s3->createPresignedRequest($cmd, '+5 minutes');

        // 6. Respond
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'upload_url' => (string) $request->getUri(),
                'object_key' => $objectKey,
                'public_url' => $this->cdnBase . '/' . $objectKey
            ]));
    }

    /**
     * Proxy upload - upload file through backend to avoid CORS issues
     * POST: file (multipart/form-data)
     */
    public function proxy_upload()
    {
        // Check if R2 is configured
        if (!$this->bucket || !$this->cdnBase) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Storage service not configured']));
        }

        // Check if file was uploaded
        if (empty($_FILES['file']['name'])) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'No file uploaded']));
        }

        $file = $_FILES['file'];
        $mime = $file['type'];
        $filename = $file['name'];

        // Validate file type
        $allowed = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf'
        ];

        if (!in_array($mime, $allowed)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Invalid file type']));
        }

        // Generate safe object key
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $objectKey = sprintf(
            'uploads/%s/%s.%s',
            date('Y/m/d'),
            bin2hex(random_bytes(16)),
            $ext
        );

        try {
            // Read file content
            $fileContent = file_get_contents($file['tmp_name']);

            // Upload to R2
            $result = $this->s3->putObject([
                'Bucket'        => $this->bucket,
                'Key'           => $objectKey,
                'Body'          => $fileContent,
                'ContentType'   => $mime,
                'CacheControl'  => 'public, max-age=31536000, immutable',
            ]);

            return $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => true,
                    'object_key' => $objectKey,
                    'public_url' => $this->cdnBase . '/' . $objectKey
                ]));
        } catch (\Exception $e) {
            log_message('error', 'R2 upload failed: ' . $e->getMessage());
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Upload failed: ' . $e->getMessage()]));
        }
    }

    /**
     * Load .env file directly as fallback
     */
    /**
     * Load .env file directly as fallback
     */
    private function _loadEnvFile()
    {
        $envPath = FCPATH . '.env';
        
        if (file_exists($envPath)) {
            $envFile = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($envFile as $line) {
                $line = trim($line);
                
                // Skip empty lines and comments
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                
                // Parse KEY=VALUE or KEY: VALUE (support both formats)
                $separator = false;
                if (strpos($line, '=') !== false) {
                    $separator = '=';
                } elseif (strpos($line, ':') !== false) {
                    $separator = ':';
                }
                
                if ($separator) {
                    list($key, $value) = explode($separator, $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    // Remove quotes if present
                    $value = trim($value, '"\''); 
                    
                    // Always set (override existing)
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}
