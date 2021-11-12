[![Tests](https://github.com/MarcelBolten/phpeggy/actions/workflows/CI-tests.yml/badge.svg)](https://github.com/MarcelBolten/phpeggy/actions/workflows/CI-tests.yml)
[![npm version](https://img.shields.io/npm/v/phpeggy)](https://www.npmjs.com/package/phpeggy)
[![License](https://img.shields.io/badge/license-mit-blue)](https://opensource.org/licenses/MIT)

# PHPeggy

A PHP code generation plugin for
[Peggy](https://github.com/peggyjs/peggy).

PHPeggy is the successor of [`phpegjs`](https://github.com/nylen/phpegjs) which had been abandoned by its maintainer.

## Migrating from `phpegjs`

There are a few API changes compared to the most recent `phpegjs` release.
- Options specific to PHPeggy have to be passed to `phpeggy` and not to `phpegjs`.
- PHP >=7.3 is required

Follow these steps to upgrade:

1. Follow the [migration instructions from Peggy](https://github.com/peggyjs/peggy#migrating-from-pegjs).
2. Uninstall `phpegjs`.
3. Replace all `require("phpegjs")` or `import ... from "phpegjs"` with `require("phpeggy")` or `import ... from "phpeggy"` as appropriate.
4. [PHPeggy-specific options](#PHPeggyOptions) are now passed to `phpeggy`:
   ```diff
   var parser = peggy.generate("start = ('a' / 'b')+", {
   -    plugins: [require("phpegjs")],
   +    plugins: [require("phpeggy")],
   -    phpegjs: { /* phpegjs-specific options */ }
   +    phpeggy: { /* phpeggy-specific options */ }
   });
   ```
5. That's it!

## Requirements

* [Peggy](https://peggyjs.org/) (known compatible with v1.2.0)

Installation
------------

### Node.js

Install Peggy with `phpeggy` plugin

```sh
$ npm install peggy@1.2.0 phpeggy
```

Usage
-----

### Generating a Parser

In Node.js, require both the Peggy parser generator and the `phpeggy` plugin:

```js
var peggy = require("peggy");
var phpeggy = require("phpeggy");
```

To generate a PHP parser, pass both the `phpeggy` plugin and your grammar to
`peggy.generate`:

```js
var parser = peggy.generate("start = ('a' / 'b')+", {
    plugins: [phpeggy]
});
```

The method will return source code of generated parser as a string. Unlike
original Peggy, generated PHP parser will be a class, not a function.

Supported options of `peggy.generate`:

  * `allowedStartRules` — rules the parser will be allowed to start parsing from
    (default: the first rule in the grammar)
  * `cache` — if `true`, makes the parser cache results, avoiding exponential
    parsing time in pathological cases but making the parser slower (default:
    `false`). In case of PHP, this is strongly recommended for big grammars
    (like javascript.pegjs or css.pegjs in example folder)
  * `grammarSource` — this object will be passed to any location() objects as the
    source property (default: undefined). This object will be used even if
    options.grammarSource is redefined in the grammar. It is useful to attach the
    file information to the errors, for example

<a name='PHPeggyOptions'></a>
You can also pass options specific to the PHPeggy plugin as follows:

```js
var parser = peggy.generate("start = ('a' / 'b')+", {
    plugins: [phpeggy],
    phpeggy: { /* phpeggy-specific options */ }
});
```

Here are the options available to pass this way:

  * `parserNamespace` - namespace of generated parser (default: `PHPeggy`). If
    value is `''` or `null`, no namespace will be used.
  * `parserClassName` - name of generated class for parser (default: `Parser`).
  * `mbstringAllowed` - whether to allow usage of PHP's `mb_*` functions which
    depend on the `mbstring` extension being installed (default: `true`). This
    can be disabled for compatibility with a wider range of PHP configurations,
    but this will also disable several features of Peggy (case-insensitive
    string matching, case-insensitive character classes, and empty character
    classes). Attempting to use these features with `mbstringAllowed: false`
    will cause `check` to throw an error.

Using the Parser
----------------

1) Save parser generated by `peggy.generate` to a file

2) In PHP code:

```php
include "your.parser.file.php";

try {
    $parser = new PHPeggy\Parser;
    $result = $parser->parse($input);
} catch (PHPeggy\SyntaxError $ex) {
    // Handle parsing error
    // [...]
}
```

You can use the following snippet to format parsing errors:

```php
catch (PHPeggy\SyntaxError $ex) {
    $message = "Syntax error: " . $ex->getMessage() . ' at line ' . $ex->grammarLine . ' column ' . $ex->grammarColumn . ' offset ' . $ex->grammarOffset;
}
```

Note that the generated PHP parser will call `preg_match_all( '/./us', ... )`
on the input string.  This may be undesirable for projects that need to
maintain compatibility with PCRE versions that are missing Unicode support
(WordPress, for example).  To avoid this call, split the input string into an
array (one array element per UTF-8 character) and pass this array into
`$parser->parse()` instead of the string input.

Grammar Syntax and Semantics
----------------------------

See documentation of [Peggy](https://github.com/peggyjs/peggy/tree/v1.2.0#grammar-syntax-and-semantics) with one difference: action blocks should be written in PHP.

Original Peggy rule:

```js
media_list = head:medium tail:("," S* medium)* {
  var result = [head];
  for (var i = 0; i < tail.length; i++) {
    result.push(tail[i][2]);
  }
  return result;
}
```

PHPeggy rule:

```php
media_list = head:medium tail:("," S* medium)* {
  $result = [$head];
  for ($i = 0; $i < count($tail); $i++) {
    $result[] = $tail[$i][2];
  }
  return $result;
}
```

To target both JavaScript and PHP with a single grammar, you can mix the two
languages using a special comment syntax:

```js
media_list = head:medium tail:("," S* medium)* {
  /** <?php
  $result = [$head];
  for ($i = 0; $i < count($tail); $i++) {
    $result[] = $tail[$i][2];
  }
  return $result;
  ?> **/

  var result = [head];
  for (var i = 0; i < tail.length; i++) {
    result.push(tail[i][2]);
  }
  return result;
}
```

You can also use the following utility functions in PHP action blocks:

- `chr_unicode($code)` - return character by its UTF-8 code (analogue of
  JavaScript's `String.fromCharCode` function).
- `ord_unicode($code)` - return the UTF-8 code for a character (analogue of
  JavaScript's `String.prototype.charCodeAt(0)` function).

Guide for converting Peggy action blocks to PHPeggy
-------------------------------------------------------

| Javascript code                   | PHP analogue                              |
| --------------------------------- | ----------------------------------------- |
| `some_var`                        | `$some_var`                               |
| `{f1: "val1", f2: "val2"}`        | `["f1" => "val1", "f2" => "val2"]`        |
| `["val1", "val2"]`                | `["val1", "val2"]`                        |
| `some_array.push("val")`          | `$some_array[] = "val"`                   |
| `some_array.length`               | `count($some_array)`                      |
| `some_array.join("")`             | `implode("", $some_array)`                |
| `some_array1.concat(some_array2)` | `array_merge($some_array1, $some_array2)` |
| `parseInt("23")`                  | `intval("23")`                            |
| `parseFloat("23.1")`              | `floatval("23.1")`                        |
| `some_str.length`                 | `mb_strlen(some_str, "UTF-8")`            |
| `some_str.replace("b", "\b")`     | `str_replace("b", "\b", $some_str)`       |
| `String.fromCharCode(2323)`       | `chr_unicode(2323)`                       |
