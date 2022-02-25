<?php
/*
 * Generated by peggy 1.0.0 with phpeggy plugin
 *
 * https://peggyjs.org/
 */

namespace PHPeggy;

/* Useful functions: */

/* chr_unicode - get unicode character from its char code */
if (!function_exists("PHPeggy\\chr_unicode")) {
    function chr_unicode($code) {
        return html_entity_decode("&#$code;", ENT_QUOTES, "UTF-8");
    }
}
/* ord_unicode - get unicode char code from string */
if (!function_exists("PHPeggy\\ord_unicode")) {
    function ord_unicode($character) {
        if (strlen($character) === 1) {
            return ord($character);
        }
        $json = json_encode($character);
        $utf16_1 = hexdec(substr($json, 3, 4));
        if (substr($json, 7, 2) === "\u") {
            $utf16_2 = hexdec(substr($json, 9, 4));
            return 0x10000 + (($utf16_1 & 0x3ff) << 10) + ($utf16_2 & 0x3ff);
        } else {
            return $utf16_1;
        }
    }
}
/* peg_regex_test - multibyte regex test */
if (!function_exists("PHPeggy\\peg_regex_test")) {
    function peg_regex_test($pattern, $string) {
        if (substr($pattern, -1) == "i") {
            return mb_eregi(substr($pattern, 1, -2), $string);
        } else {
            return mb_ereg(substr($pattern, 1, -1), $string);
        }
    }
}

/* Syntax error exception */
if (!class_exists("PHPeggy\\SyntaxError", false)) {
    class SyntaxError extends \Exception {
        public $expected;
        public $found;
        public $grammarOffset;
        public $grammarLine;
        public $grammarColumn;
        public $name;
        public function __construct($message, $expected, $found, $offset, $line, $column) {
            parent::__construct($message, 0);
            $this->expected = $expected;
            $this->found = $found;
            $this->grammarOffset = $offset;
            $this->grammarLine = $line;
            $this->grammarColumn = $column;
            $this->name = "SyntaxError";
        }
    }
}

class Parser {
    private $peg_currPos          = 0;
    private $peg_reportedPos      = 0;
    private $peg_cachedPos        = 0;
    private $peg_cachedPosDetails = array('line' => 1, 'column' => 1, 'seenCR' => false );
    private $peg_maxFailPos       = 0;
    private $peg_maxFailExpected  = array();
    private $peg_silentFails      = 0;
    private $input                = array();
    private $input_length         = 0;

    private function cleanup_state() {
      $this->peg_currPos          = 0;
      $this->peg_reportedPos      = 0;
      $this->peg_cachedPos        = 0;
      $this->peg_cachedPosDetails = array('line' => 1, 'column' => 1, 'seenCR' => false );
      $this->peg_maxFailPos       = 0;
      $this->peg_maxFailExpected  = array();
      $this->peg_silentFails      = 0;
      $this->input                = array();
      $this->input_length         = 0;

    }

    private function input_substr($start, $length) {
      if ($length === 1 && $start < $this->input_length) {
        return $this->input[$start];
      }
      $substr = '';
      $max = min($start + $length, $this->input_length);
      for ($i = $start; $i < $max; $i++) {
        $substr .= $this->input[$i];
      }
      return $substr;
    }


    private function text() {
      return $this->input_substr($this->peg_reportedPos, $this->peg_currPos - $this->peg_reportedPos);
    }

    private function offset() {
      return $this->peg_reportedPos;
    }

    private function line() {
      $compute_pd = $this->peg_computePosDetails($this->peg_reportedPos);
      return $compute_pd["line"];
    }

    private function column() {
      $compute_pd = $this->peg_computePosDetails($this->peg_reportedPos);
      return $compute_pd["column"];
    }

    private function expected($description) {
      throw $this->peg_buildException(
        null,
        array(array("type" => "other", "description" => $description )),
        $this->peg_reportedPos
      );
    }

    private function error($message) {
      throw $this->peg_buildException($message, null, $this->peg_reportedPos);
    }

    private function peg_advancePos(&$details, $startPos, $endPos) {
      for ($p = $startPos; $p < $endPos; $p++) {
        $ch = $this->input_substr($p, 1);
        if ($ch === "\n") {
          if (!$details["seenCR"]) { $details["line"]++; }
          $details["column"] = 1;
          $details["seenCR"] = false;
        } else if ($ch === "\r" || $ch === "\u2028" || $ch === "\u2029") {
          $details["line"]++;
          $details["column"] = 1;
          $details["seenCR"] = true;
        } else {
          $details["column"]++;
          $details["seenCR"] = false;
        }
      }
    }

    private function peg_computePosDetails($pos) {
      if ($this->peg_cachedPos !== $pos) {
        if ($this->peg_cachedPos > $pos) {
          $this->peg_cachedPos = 0;
          $this->peg_cachedPosDetails = array( "line" => 1, "column" => 1, "seenCR" => false );
        }
        $this->peg_advancePos($this->peg_cachedPosDetails, $this->peg_cachedPos, $pos);
        $this->peg_cachedPos = $pos;
      }

      return $this->peg_cachedPosDetails;
    }

    private function peg_fail($expected) {
      if ($this->peg_currPos < $this->peg_maxFailPos) { return; }

      if ($this->peg_currPos > $this->peg_maxFailPos) {
        $this->peg_maxFailPos = $this->peg_currPos;
        $this->peg_maxFailExpected = array();
      }

      $this->peg_maxFailExpected[] = $expected;
    }

    private function peg_buildException_expectedComparator($a, $b) {
      if ($a["description"] < $b["description"]) {
        return -1;
      } else if ($a["description"] > $b["description"]) {
        return 1;
      } else {
        return 0;
      }
    }

    private function peg_buildException($message, $expected, $pos) {
      $posDetails = $this->peg_computePosDetails($pos);
      $found      = $pos < $this->input_length ? $this->input[$pos] : null;

      if ($expected !== null) {
        usort($expected, array($this, "peg_buildException_expectedComparator"));
        $i = 1;
        while ($i < count($expected)) {
          if ($expected[$i - 1] === $expected[$i]) {
            array_splice($expected, $i, 1);
          } else {
            $i++;
          }
        }
      }

      if ($message === null) {
        $expectedDescs = array_fill(0, count($expected), null);

        for ($i = 0; $i < count($expected); $i++) {
          $expectedDescs[$i] = $expected[$i]["description"];
        }

        $expectedDesc = count($expected) > 1
          ? join(", ", array_slice($expectedDescs, 0, -1))
              . " or "
              . $expectedDescs[count($expected) - 1]
          : $expectedDescs[0];

        $foundDesc = $found ? json_encode($found) : "end of input";

        $message = "Expected " . $expectedDesc . " but " . $foundDesc . " found.";
      }

      return new SyntaxError(
        $message,
        $expected,
        $found,
        $pos,
        $posDetails["line"],
        $posDetails["column"]
      );
    }

    private $peg_FAILED;
    private $peg_c0;
    private $peg_c1;
    private $peg_c2;
    private $peg_c3;
    private $peg_c4;
    private $peg_c5;
    private $peg_c6;
    private $peg_c7;
    private $peg_c8;
    private $peg_c9;

    private function peg_f0($hex_code) {
    		$chr = _chr_unicode( hexdec( implode( '', $hex_code ) ) );
    		return array(
    			'chr' => $chr,
    			'ord' => _ord_unicode( $chr ),
    		);
    	}
    private function peg_f1($delim, $text) {
    		return $delim . implode( '', $text );
    	}
    private function peg_f2($content) {
    		return implode( '', $content );
    	}

    private function peg_parseDocument() {

      $s0 = array();
      $s1 = $this->peg_parseThing();
      if ($s1 !== $this->peg_FAILED) {
        while ($s1 !== $this->peg_FAILED) {
          $s0[] = $s1;
          $s1 = $this->peg_parseThing();
        }
      } else {
        $s0 = $this->peg_FAILED;
      }

      return $s0;
    }

    private function peg_parseThing() {

      $s0 = $this->peg_parseChr_Unicode_Test();
      if ($s0 === $this->peg_FAILED) {
        $s0 = $this->peg_parseComment();
        if ($s0 === $this->peg_FAILED) {
          $s0 = $this->peg_parseWhitespace();
        }
      }

      return $s0;
    }

    private function peg_parseChr_Unicode_Test() {

      $s0 = $this->peg_currPos;
      if ($this->input_substr($this->peg_currPos, 2) === $this->peg_c0) {
        $s1 = $this->peg_c0;
        $this->peg_currPos += 2;
      } else {
        $s1 = $this->peg_FAILED;
        if ($this->peg_silentFails === 0) {
            $this->peg_fail($this->peg_c1);
        }
      }
      if ($s1 !== $this->peg_FAILED) {
        $s2 = array();
        if (peg_regex_test($this->peg_c2, $this->input_substr($this->peg_currPos, 1))) {
          $s3 = $this->input_substr($this->peg_currPos, 1);
          $this->peg_currPos++;
        } else {
          $s3 = $this->peg_FAILED;
          if ($this->peg_silentFails === 0) {
              $this->peg_fail($this->peg_c3);
          }
        }
        if ($s3 !== $this->peg_FAILED) {
          while ($s3 !== $this->peg_FAILED) {
            $s2[] = $s3;
            if (peg_regex_test($this->peg_c2, $this->input_substr($this->peg_currPos, 1))) {
              $s3 = $this->input_substr($this->peg_currPos, 1);
              $this->peg_currPos++;
            } else {
              $s3 = $this->peg_FAILED;
              if ($this->peg_silentFails === 0) {
                  $this->peg_fail($this->peg_c3);
              }
            }
          }
        } else {
          $s2 = $this->peg_FAILED;
        }
        if ($s2 !== $this->peg_FAILED) {
          $this->peg_reportedPos = $s0;
          $s1 = $this->peg_f0($s2);
          $s0 = $s1;
        } else {
          $this->peg_currPos = $s0;
          $s0 = $this->peg_FAILED;
        }
      } else {
        $this->peg_currPos = $s0;
        $s0 = $this->peg_FAILED;
      }

      return $s0;
    }

    private function peg_parseComment() {

      $s0 = $this->peg_currPos;
      if ($this->input_substr($this->peg_currPos, 2) === $this->peg_c4) {
        $s1 = $this->peg_c4;
        $this->peg_currPos += 2;
      } else {
        $s1 = $this->peg_FAILED;
        if ($this->peg_silentFails === 0) {
            $this->peg_fail($this->peg_c5);
        }
      }
      if ($s1 !== $this->peg_FAILED) {
        $s2 = array();
        if (peg_regex_test($this->peg_c6, $this->input_substr($this->peg_currPos, 1))) {
          $s3 = $this->input_substr($this->peg_currPos, 1);
          $this->peg_currPos++;
        } else {
          $s3 = $this->peg_FAILED;
          if ($this->peg_silentFails === 0) {
              $this->peg_fail($this->peg_c7);
          }
        }
        while ($s3 !== $this->peg_FAILED) {
          $s2[] = $s3;
          if (peg_regex_test($this->peg_c6, $this->input_substr($this->peg_currPos, 1))) {
            $s3 = $this->input_substr($this->peg_currPos, 1);
            $this->peg_currPos++;
          } else {
            $s3 = $this->peg_FAILED;
            if ($this->peg_silentFails === 0) {
                $this->peg_fail($this->peg_c7);
            }
          }
        }
        if ($s2 !== $this->peg_FAILED) {
          $this->peg_reportedPos = $s0;
          $s1 = $this->peg_f1($s1, $s2);
          $s0 = $s1;
        } else {
          $this->peg_currPos = $s0;
          $s0 = $this->peg_FAILED;
        }
      } else {
        $this->peg_currPos = $s0;
        $s0 = $this->peg_FAILED;
      }

      return $s0;
    }

    private function peg_parseWhitespace() {

      $s0 = $this->peg_currPos;
      $s1 = array();
      if (peg_regex_test($this->peg_c8, $this->input_substr($this->peg_currPos, 1))) {
        $s2 = $this->input_substr($this->peg_currPos, 1);
        $this->peg_currPos++;
      } else {
        $s2 = $this->peg_FAILED;
        if ($this->peg_silentFails === 0) {
            $this->peg_fail($this->peg_c9);
        }
      }
      if ($s2 !== $this->peg_FAILED) {
        while ($s2 !== $this->peg_FAILED) {
          $s1[] = $s2;
          if (peg_regex_test($this->peg_c8, $this->input_substr($this->peg_currPos, 1))) {
            $s2 = $this->input_substr($this->peg_currPos, 1);
            $this->peg_currPos++;
          } else {
            $s2 = $this->peg_FAILED;
            if ($this->peg_silentFails === 0) {
                $this->peg_fail($this->peg_c9);
            }
          }
        }
      } else {
        $s1 = $this->peg_FAILED;
      }
      if ($s1 !== $this->peg_FAILED) {
        $this->peg_reportedPos = $s0;
        $s1 = $this->peg_f2($s1);
      }
      $s0 = $s1;

      return $s0;
    }

  public function parse($input) {
    $arguments = func_get_args();
    $options = count($arguments) > 1 ? $arguments[1] : array();
    $this->cleanup_state();

    if (is_array($input)) {
        $this->input = $input;
    } else {
        preg_match_all("/./us", $input, $match);
        $this->input = $match[0];
    }
    $this->input_length = count($this->input);

    $old_regex_encoding = mb_regex_encoding();
    mb_regex_encoding("UTF-8");

    $this->peg_FAILED = new \stdClass;
    $this->peg_c0 = "\\x";
    $this->peg_c1 = array( "type" => "literal", "value" => "\\x", "description" => "\"\\\\x\"" );
    $this->peg_c2 = "/^[0-9a-f]/i";
    $this->peg_c3 = array( "type" => "class", "value" => "[0-9a-f]", "description" => "[0-9a-f]" );
    $this->peg_c4 = "//";
    $this->peg_c5 = array( "type" => "literal", "value" => "//", "description" => "\"//\"" );
    $this->peg_c6 = "/^[^\\r\\n]/";
    $this->peg_c7 = array( "type" => "class", "value" => "[\r\n]", "description" => "[\r\n]" );
    $this->peg_c8 = "/^[ \\t\\r\\n]/";
    $this->peg_c9 = array( "type" => "class", "value" => "[ \t\r\n]", "description" => "[ \t\r\n]" );

    $peg_startRuleFunctions = array( 'Document' => array($this, "peg_parseDocument") );
    $peg_startRuleFunction  = array($this, "peg_parseDocument");
    if (isset($options["startRule"])) {
      if (!(isset($peg_startRuleFunctions[$options["startRule"]]))) {
        throw new \Exception("Can't start parsing from rule \"" . $options["startRule"] . "\".");
      }

      $peg_startRuleFunction = $peg_startRuleFunctions[$options["startRule"]];
    }

    /* BEGIN initializer code */


    function _chr_unicode( $ch ) {
    	if ( function_exists( 'php52_compat_chr_unicode' ) ) {
    		return php52_compat_chr_unicode( $ch );
    	}
    	return chr_unicode( $ch );
    }

    function _ord_unicode( $ch ) {
    	if ( function_exists( 'php52_compat_ord_unicode' ) ) {
    		return php52_compat_ord_unicode( $ch );
    	}
    	return ord_unicode( $ch );
    }


    /* END initializer code */

    $peg_result = call_user_func($peg_startRuleFunction);

    mb_regex_encoding($old_regex_encoding);

    if ($peg_result !== $this->peg_FAILED && $this->peg_currPos === $this->input_length) {
      $this->cleanup_state(); // Free up memory
      return $peg_result;
    } else {
      if ($peg_result !== $this->peg_FAILED && $this->peg_currPos < $this->input_length) {
        $this->peg_fail(array("type" => "end", "description" => "end of input" ));
      }

      $exception = $this->peg_buildException(null, $this->peg_maxFailExpected, $this->peg_maxFailPos);
      $this->cleanup_state(); // Free up memory
      throw $exception;
    }
  }

};