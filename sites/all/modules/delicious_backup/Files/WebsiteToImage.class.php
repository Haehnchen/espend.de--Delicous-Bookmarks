<?php

class WebsiteToImage {
  const FORMAT_JPG = 'jpg';
  const FORMAT_JPEG = 'jpeg';
  const FORMAT_PNG = 'png';
  const FORMAT_TIF = 'tif';
  const FORMAT_TIFF = 'tiff';

  protected $_programPath = '/root';
  protected $_outputFile;
  protected $_url;
  protected $_format = self::FORMAT_JPG;
  protected $_quality = 75;
  protected $_crop_h = 1024;
  protected $_crop_w = 1024;
  protected $formats = array('jpg', 'jpeg', 'png', 'tif', 'tiff', 'pdf');
  
  protected $binaries = array(
     'image' => 'wkhtmltoimage-i386',
     'pdf' => 'wkhtmltopdf-i386',
  );

  public function start() {
    $programPath = escapeshellcmd($this->_programPath) . '/' . $this->binaries['image'];
    $outputFile = escapeshellarg($this->_outputFile);
    $url = escapeshellarg($this->_url);
    $format = escapeshellarg($this->_format);
    $quality = escapeshellarg($this->_quality);
    $crop_h = escapeshellarg($this->_crop_h);
    $crop_w = escapeshellarg($this->_crop_w);


    $command = "$programPath --format $format --quality $quality  --crop-w $crop_w --crop-h $crop_h $url $outputFile 2>&1";

    if ($this->_format == 'pdf') {
      $programPath = escapeshellcmd($this->_programPath) . '/' .$this->binaries['pdf'];
      $command = "$programPath --footer-center 'espend.de on [date] [time] - [webpage]' --footer-font-size 6 -l $url $outputFile 2>&1";
    }
    

    
    #echo execwaittimeout($command);
    $return = $this->execwaittimeout($command, 30);
    
    $this->CheckError($return);
    
    return true;
  }

  private function CheckError($cmd_str) {
    
    if(preg_match('/error:(.*)/i', $cmd_str, $res))
      throw new Exception(trim($res[0]));
    
    if(!preg_match('/done/i', $cmd_str))
      throw new Exception(preg_replace('/\n+|\r+/', ' ', $cmd_str));
    
  }
  
  public function setOutputFile($outputFile) {

    $info = pathinfo($outputFile);
    if (!in_array($info['extension'], $this->formats)) {
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

  public function getOutputFile() {
    return $this->_outputFile;
  }

  public function setProgramPath($programPath) {
    $this->_programPath = $programPath;
    return $this;
  }

  public function getProgramPath() {
    return $this->_programPath;
  }

  public function setFormat($format) {
    $this->_format = $format;
    return $this;
  }

  public function getFormat() {
    return $this->_format;
  }

  public function setQuality($quality) {
    $this->_quality = (int) $quality;
    return $this;
  }

  public function getQuality() {
    return $this->_quality;
  }

  public function setUrl($url) {
    $this->_url = $url;
    return $this;
  }

  public function getUrl() {
    return $this->_url;
  }

  /**
   * Execute a command and kill it if the timeout limit fired to prevent long php execution
   *
   * @see http://stackoverflow.com/questions/2603912/php-set-timeout-for-script-with-system-call-set-time-limit-not-working
   *
   * @param string $cmd Command to exec (you should use 2>&1 at the end to pipe all output)
   * @param integer $timeout
   * @return string Returns command output
   */
  private function ExecWaitTimeout($cmd, $timeout=5) {

    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );
    $pipes = array();

    $timeout += time();
    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (!is_resource($process)) {
      throw new Exception("proc_open failed on: " . $cmd);
    }

    $output = '';

    do {
      $timeleft = $timeout - time();
      $read = array($pipes[1]);
      stream_select($read, $write = NULL, $exeptions = NULL, $timeleft, NULL);

      if (!empty($read)) {
        $output .= fread($pipes[1], 8192);
      }
    } while (!feof($pipes[1]) && $timeleft > 0);

    if ($timeleft <= 0) {
      proc_terminate($process);
      throw new Exception("command timeout on: " . $cmd);
    } else {
      return $output;
    }
  }

}

?>
