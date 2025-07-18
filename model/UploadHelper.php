<?php

class UploadHelper {
    private $file;
    private static $instance = null;
    private $fileFor;
    private $path;

    const PROFILE_IMAGE = 1;

    const UPLOAD_DIR = 'uploads/';
    const OTHER_FILE_INFO = [
        self::PROFILE_IMAGE => [
            'path' => self::UPLOAD_DIR . 'profile_images/',
            'max_size' => [
                'size' => 5 * 1024 * 1024, // 5MB
                'text' => '5MB'
            ],
            'allowed_types' => [
                'type' =>['image/jpeg', 'image/png', 'image/webp'],
                'text' => 'JPG, PNG, WEBP'
            ],
            'type' => 'image',
            'image_quality_type' => [
                'is_square' => true, // Whether to crop to square
                'max_dimension' => [
                    'width' => 720, // Maximum width for resizing
                    'height' => 720 // Maximum height for resizing
                ], // Maximum dimension for resizing
                'quality' => 85 // Default quality for images
            ], // Default quality for images
        ]
    ];


    private function __construct() {
        $this->fileFor = self::PROFILE_IMAGE; // Default file type
        $this->file = null; // Initialize file as null
        $this->path = self::UPLOAD_DIR.'default/'; // Set default path
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setFileFor($fileFor) {
        if (isset(self::OTHER_FILE_INFO[$fileFor])) {
            $this->fileFor = $fileFor;
        } else {
            throw new Exception("Invalid file type specified.");
        }
    }


    public function setFile($filename,$root=true) {
        if ($root) {
            $file = $_FILES[$filename] ?? null;
        } else {
            $file = $filename;
        }
        if (is_array($file) && isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $this->file = $file;
        } else {
            throw new Exception("Invalid file provided.");
        }

        if (isset(self::OTHER_FILE_INFO[$this->fileFor]['path'])) {
            $this->path = self::OTHER_FILE_INFO[$this->fileFor]['path'];
        }

    }



    /**
     * Validates the uploaded file.
     *
     * @param array $file The uploaded file information from $_FILES.
     * @return array An array containing 'status' and 'message'.
     */
    public function validateUpload() {
        if (!$this->file) {
            throw new Exception("No file set for validation.");
        }
        $file = $this->file;
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error.");
        }

        // Check file size (limit to 5MB)
        if (
            !empty(self::OTHER_FILE_INFO[$this->fileFor]['max_size']) &&
            self::OTHER_FILE_INFO[$this->fileFor]['max_size']['size'] > 0 &&    
            self::OTHER_FILE_INFO[$this->fileFor]['max_size']['size'] < $file['size']
            ) {
            throw new Exception("File size exceeds the limit of " . self::OTHER_FILE_INFO[$this->fileFor]['max_size']['text'] . ".");
        }

        // Check file type (allow only images)
        if (
            self::OTHER_FILE_INFO[$this->fileFor]['allowed_types'] &&
            !empty($file['type']) &&
            !empty(self::OTHER_FILE_INFO[$this->fileFor]['allowed_types']['text']) &&
            !empty(self::OTHER_FILE_INFO[$this->fileFor]['allowed_types']['type']) &&
            !in_array($file['type'], self::OTHER_FILE_INFO[$this->fileFor]['allowed_types']['type'])) {
            throw new Exception("Invalid file type. Only " . self::OTHER_FILE_INFO[$this->fileFor]['allowed_types']['text'] . " are allowed.");
        }

        if(self::OTHER_FILE_INFO[$this->fileFor]['type'] === 'image') {
            // Check if the file is a valid image
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception("Uploaded file is not a valid image.");
            }
            // Check if the image dimensions are within acceptable limits
            if ($imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
                throw new Exception("Image dimensions exceed the maximum allowed size of 5000x5000 pixels.");
            }
        }
        return [
            'status' => true,
            'message' => 'File is valid.'
        ];

    }

    public function convertToRecommended() {
        if(self::OTHER_FILE_INFO[$this->fileFor]['type'] === 'image') {
            switch ($this->file['type']) {
                case 'image/jpeg':
                    $img = imagecreatefromjpeg($this->file['tmp_name']);
                    break;
                case 'image/png':
                    $img = imagecreatefrompng($this->file['tmp_name']);
                    break;
                case 'image/webp':
                    $img = imagecreatefromwebp($this->file['tmp_name']);
                    break;
                default:
                    throw new Exception("Unsupported file type.");
            }
            if (!$img) {
                throw new Exception("Failed to create image from uploaded file.");
            }
            if(self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']) {
                if(isset(self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['is_square']) && self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['is_square']) {
                    $width = imagesx($img);
                    $height = imagesy($img);
                    $side = min($width, $height);
                    $src_x = ($width > $height) ? (($width - $height) / 2) : 0;
                    $src_y = ($height > $width) ? (($height - $width) / 2) : 0;
                    $cropped = imagecreatetruecolor($side, $side);
                    imagecopyresampled($cropped, $img, 0, 0, $src_x, $src_y, $side, $side, $side, $side);
                    imagedestroy($img);
                    $img = $cropped;
                }

                if(isset(self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['max_dimension']) && self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['max_dimension']) {
                    // Resize image if it exceeds max dimension
                    $maxDim = self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['max_dimension'];
                    $width = imagesx($img);
                    $height = imagesy($img);
                    if ($width > $maxDim['width'] || $height > $maxDim['height']) {
                        $ratio = min($maxDim['width'] / $width, $maxDim['height'] / $height);
                        $newWidth = (int)($width * $ratio);
                        $newHeight = (int)($height * $ratio);
                        $resized = imagecreatetruecolor($newWidth, $newHeight);
                        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                        imagedestroy($img);
                        $img = $resized;
                    }
                }
                if(self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['quality'] && isset(self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['quality'])) {
                    // Convert to JPEG with specified quality
                    imagejpeg($img, $this->file['tmp_name'], self::OTHER_FILE_INFO[$this->fileFor]['image_quality_type']['quality']);
                }else {
                    // Default quality if not specified
                    
                }
            }
        }
        return $img;
    }

    private function initDirectoryIndex($path)
    {
        if( ! @mkdir($path, 0755) && !is_dir($path) ){
            throw new Exception(sprintf('Permission denied on %s', $path));
        }

        # To prevent directory listing
        $files = ['index.php' => '', '.htaccess' => 'Options -Indexes'];

        foreach ($files as $tmpPath => $content){
            $tmpPath = sprintf('%s/%s', $path, $tmpPath);
            file_put_contents($tmpPath, $content);
        }
    }

    public function saveFile($prefix = '') {
        if (!$this->file) {
            throw new Exception("No file set for saving.");
        }
        $this->initDirectoryIndex($this->path);
        $targetPath = $this->path . $this->getFileName($prefix);
        if (move_uploaded_file($this->file['tmp_name'], $targetPath)) {
            return [
                'response_status' => true,
                'message' => 'File uploaded successfully.',
                'path' => $targetPath
            ];
        } else {
            throw new Exception("Failed to move uploaded file.");
        }
    }

    private function getFileName($prefix='') {
        switch ($this->fileFor) {
            case self::PROFILE_IMAGE:
                $prefix = 'profile_'.$prefix?$prefix.'_':'';
                break;
            default:
                $prefix = 'other_'.$prefix?$prefix.'_':'';
                break;
        }
        return $prefix . basename($this->file['name']);
    }


}