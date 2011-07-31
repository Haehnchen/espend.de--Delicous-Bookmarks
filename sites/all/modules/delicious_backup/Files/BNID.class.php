<?php

/**
 * http://ken-soft.com/2010/08/31/base-62-10-conversion-class-php/
 * 
 */
class BNID {

  // Alphabet of Base N (default is a Base 62 Implementation)
  var $bN = array(
    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
    'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
  );
  var $baseN;

  /**
   * pass in your own alphabet of any base following above examples
   *  base should be greater than one
   * @param array $alphabet
   */
  function __construct($alphabet=null) {
    if ($alphabet) {
      $this->bN = $alphabet;
    }
    $this->baseN = count($this->bN);
  }

  // convert base 10 to base N
  function base10ToN($b10num=0) {
    $bNnum = "";
    do {
      $bNnum = $this->bN[$b10num % $this->baseN] . $bNnum;
      $b10num /= $this->baseN;
    } while ($b10num >= 1);
    return $bNnum;
  }

  // convert base N to base 10
  function baseNTo10($bNnum = "") {
    $b10num = 0;
    $len = strlen($bNnum);
    for ($i = 0; $i < $len; $i++) {
      $val = array_keys($this->bN, substr($bNnum, $i, 1));
      $b10num += $val[0] * pow($this->baseN, $len - $i - 1);
    }
    return $b10num;
  }

}

?>
