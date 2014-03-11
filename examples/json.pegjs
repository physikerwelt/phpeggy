/* 
 * ! This is php-compatible version of grammar "pegjs/examples/json.pegjs"
 *
 * JSON parser based on the grammar described at http://json.org/. 
 */

/* ===== Syntactical Elements ===== */

start
  = _ object:object { return $object; }

object
  = "{" _ "}" _                 { return array();      }
  / "{" _ members:members "}" _ { return $members; }

members
  = head:pair tail:("," _ pair)* {
      $result = array();
      $result[$head[0]] = $head[1];
      for ($i = 0; $i < count($tail); $i++) {
        $result[$tail[$i][2][0]] = $tail[$i][2][1];
      }
      return $result;
    }

pair
  = name:string ":" _ value:value { return array($name, $value); }

array
  = "[" _ "]" _                   { return array();       }
  / "[" _ elements:elements "]" _ { return $elements; }

elements
  = head:value tail:("," _ value)* {
      $result = array($head);
      for ($i = 0; $i < count($tail); $i++) {
        $result[] = $tail[$i][2];
      }
      return $result;
    }

value
  = string
  / number
  / object
  / array
  / "true" _  { return true;  }
  / "false" _ { return false; }
  / "null" _  { return null;  }

/* ===== Lexical Elements ===== */

string "string"
  = '"' '"' _             { return "";    }
  / '"' chars:chars '"' _ { return $chars; }

chars
  = chars:char+ { return join("", $chars); }

char
  // In the original JSON grammar: "any-Unicode-character-except-"-or-\-or-control-character"
  = [^"\\\0-\x1F\x7f]
  / '\\"'  { return '"';  }
  / "\\\\" { return "\\"; }
  / "\\/"  { return "/";  }
  / "\\b"  { return "\b"; }
  / "\\f"  { return "\f"; }
  / "\\n"  { return "\n"; }
  / "\\r"  { return "\r"; }
  / "\\t"  { return "\t"; }
  / "\\u" digits:$(hexDigit hexDigit hexDigit hexDigit) {
      return chr_unicode(intval($digits, 16));
    }

number "number"
  = parts:$(int frac exp) _ { return floatval($parts); }
  / parts:$(int frac) _     { return floatval($parts); }
  / parts:$(int exp) _      { return floatval($parts); }
  / parts:$(int) _          { return floatval($parts); }

int
  = digit19 digits
  / digit
  / "-" digit19 digits
  / "-" digit

frac
  = "." digits

exp
  = e digits

digits
  = digit+

e
  = [eE] [+-]?

/*
 * The following rules are not present in the original JSON gramar, but they are
 * assumed to exist implicitly.
 *
 * FIXME: Define them according to ECMA-262, 5th ed.
 */

digit
  = [0-9]

digit19
  = [1-9]

hexDigit
  = [0-9a-fA-F]

/* ===== Whitespace ===== */

_ "whitespace"
  = whitespace*

// Whitespace is undefined in the original JSON grammar, so I assume a simple
// conventional definition consistent with ECMA-262, 5th ed.
whitespace
  = [ \t\n\r]