<?php
class WebsiteToImage
{
    const FORMAT_JPG  = 'jpg';
    const FORMAT_JPEG = 'jpeg';
    const FORMAT_PNG  = 'png';
    const FORMAT_TIF  = 'tif';
    const FORMAT_TIFF = 'tiff';
 
    protected $_programPath;
    protected $_outputFile;
    protected $_url;
    protected $_format = self::FORMAT_JPG;
    protected $_quality = 75;
    protected $_crop_h = 1024;
    protected $_crop_w = 1024;
    
    protected $formats = array('jpg', 'jpeg', 'png', 'tif', 'tiff');
 
    public function start()
    {
        $programPath = escapeshellcmd($this->_programPath);
        $outputFile  = escapeshellarg($this->_outputFile);
        $url         = escapeshellarg($this->_url);
        $format      = escapeshellarg($this->_format);
        $quality     = escapeshellarg($this->_quality);
        $crop_h     = escapeshellarg($this->_crop_h);
        $crop_w     = escapeshellarg($this->_crop_w);
 
        $command = "$programPath --format $format --quality $quality  --crop-w $crop_w --crop-h $crop_h $url $outputFile";
        echo $command;
        
        exec($command);
    }

    public function setOutputFile($outputFile)
    {

        $info = pathinfo($outputFile);
        if(!in_array($info['extension'], $this->formats)) {
          throw new Exception('output extension unknown');
        }
        
        $this->_format = $info['extension'];
              

        clearstatcache();
        if (!is_writable(dirname($outputFile))) {
            throw new Exception('output file not writable');
        }
         
        $this->_outputFile = $outputFile;
      
        return $this;
    }
 
    public function getOutputFile()
    {
        return $this->_outputFile;
    }
 
    public function setProgramPath($programPath)
    {
        $this->_programPath = $programPath;
        return $this;
    }
 
    public function getProgramPath()
    {
        return $this->_programPath;
    }
 
    public function setFormat($format)
    {
        $this->_format = $format;
        return $this;
    }
 
    public function getFormat()
    {
        return $this->_format;
    }
 
    public function setQuality($quality)
    {
        $this->_quality = (int)$quality;
        return $this;
    }
 
    public function getQuality()
    {
        return $this->_quality;
    }
 
    public function setUrl($url)
    {
        $this->_url = $url;
        return $this;
    }
 
    public function getUrl()
    {
        return $this->_url;
    }
}
?>
