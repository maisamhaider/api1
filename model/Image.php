<?php

class ImageException extends PDOException
{

}

class image
{

    private $_id;
    private $_title;
    private $_filename;
    private $_mimetype;
    private $_taskid;
    private $_uploadFolderLocation;

    /**
     * image constructor.
     * @param $_id
     * @param $_title
     * @param $_filename
     * @param $_mimetype
     * @param $_taskid
     * @param $_uploadFolderLocation
     */
    public function __construct($id, $title, $filename, $mimetype, $taskid)
    {
        $this->setId($id);
        $this->setTitle($title);
        $this->setFilename($filename);
        $this->setMimetype($mimetype);
        $this->setTaskId($taskid);
        $this->_uploadFolderLocation = "../../../../taskimages/";
    }


    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->_filename;
    }

    /**
     * @return mixed
     */
    public function getFileExtension()
    {
        $fileNamePart = explode(".", $this->_filename);
        $lastArrayElement = count($fileNamePart) - 1;
        return $fileNamePart[$lastArrayElement];
    }

    /**
     * @return mixed
     */
    public function getMimetype()
    {
        return $this->_mimetype;
    }

    /**
     * @return mixed
     */
    public function getTaskid()
    {
        return $this->_taskid;
    }

    /**
     * @return mixed
     */
    public function getUploadFolderLocation()
    {
        return $this->_uploadFolderLocation;
    }

    public function returnImageFile()
    {
        $imageFile = $this->getUploadFolderLocation() . $this->getTaskid() . "/" . $this->getFilename();
        if (!file_exists($imageFile)) {
            throw new ImageException("Image file not found");
        }
        header("Content-Type: ", $this->getMimetype());
        header('Content-Disposition: inline; filename="' . $this->getFilename() . '"');
        if (!readfile($imageFile)) {
            http_response_code(404);
            exit;
        }
    }

    public function getImageUrl()
    {
        //http://localhost/api1/v1/tasks/1/images/1
        $httpOrHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];
        $url = "/api1/v1/tasks/" . $this->getTaskid() . "/images/" . $this->getId();
        return $httpOrHttps . "://" . $host . $url;
    }

    //setters

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {

        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new ImageException("Image Id Error");
        }
        $this->_id = $id;

//        if(($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
//            throw new ImageException("Image ID error");
//        }
//        $this->_id = $id;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title): void
    {
        if (strlen($title) < 1 || strlen($title) > 255) {
            throw new ImageException("Image Title Error");
        }
        $this->_title = $title;
    }

    /**
     * @param mixed $filename
     */
    public function setFilename($filename): void
    {
        if (strlen($filename) < 1 || strlen($filename) > 30 || preg_match("/^[a-zA-Z0-9_-]+(.png|.jpg|.gif)$/",
                $filename) != 1) {
            throw new ImageException("Image file error - file name must between 1 and 30 characters and and only
            be .jpg, .png and .gif");
        }
        $this->_filename = $filename;
    }


    /**
     * @param mixed $mimetype
     */
    public function setMimetype($mimetype): void
    {
        if (strlen($mimetype) < 1 || strlen($mimetype) > 255) {
            throw new ImageException("Image Mimetype error" . $mimetype);
        }
        $this->_mimetype = $mimetype;
    }

    /**
     * @param mixed $taskid
     */
    public function setTaskId($taskid): void
    {
        if (($taskid !== null) && (!is_numeric($taskid) || $taskid <= 0 || $taskid > 9223372036854775807 ||
                $this->_taskid !== null)) {
            throw new ImageException("Image task ID Error");
        }
        $this->_taskid = $taskid;
    }


    public function saveImageFile($tmpFile)
    {
        $uploadFilePath = $this->getUploadFolderLocation() . $this->getTaskid() . "/" . $this->getFilename();
        if (!is_dir($this->getUploadFolderLocation() . $this->getTaskid())) {
            if (!mkdir($this->getUploadFolderLocation() . $this->getTaskid())) {
                throw new ImageException("Failed to create image upload folder");
            }
        }

        if (!file_exists($tmpFile)) {
            throw new ImageException("Failed to upload image file");
        }
        if (!move_uploaded_file($tmpFile, $uploadFilePath)) {
            throw new ImageException("Failed to upload image file");
        }
    }


    public function getImageArrayData(): array
    {
        $imageArray = array();
        $imageArray['id'] = $this->getId();
        $imageArray['title'] = $this->getTitle();
        $imageArray['filename'] = $this->getFilename();
        $imageArray['mimetype'] = $this->getMimetype();
        $imageArray['taskid'] = $this->getTaskid();
        $imageArray['image_url'] = $this->getImageUrl();
        return $imageArray;
    }

}
